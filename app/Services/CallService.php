<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\CallAttemptOutcome;
use App\Constants\CallAttemptStatus;
use App\Models\CallAttempt;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Twilio\OutboundCallClient;
use App\Services\Twilio\OutboundCallFailed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;
use Twilio\TwiML\VoiceResponse;

/**
 * Click-to-Call fuer einen gekauften Lead (FB-081) und das Protokoll der
 * Versuche (FB-082).
 *
 * Der Ablauf ist zweistufig: Twilio ruft zuerst den Mitarbeiter auf seiner
 * bestaetigten Nummer an und stellt ihn danach zum Lead durch. Die
 * Bestaetigung der eigenen Nummer bleibt Voraussetzung -- sie belegt, dass das
 * Portal einen erreichbaren Menschen anruft und nicht ins Leere waehlt.
 *
 * Beim Lead erscheint die Portal-Nummer als Rufnummernanzeige, nicht die des
 * Mitarbeiters: Bis die Erreichbarkeit entschieden und der Lead abgerechnet
 * ist, bleibt der Kaeufer maskiert (Ticket #7; Freigabe folgt in FB-085).
 *
 * Bewertet wird hier nichts. Ob ein Versuch als erreicht zaehlt, entscheidet
 * der AttemptClassifier anhand von `duration_seconds` an genau einer Stelle
 * (FB-083); dieser Dienst schreibt nur mit, was passiert ist -- samt Rohmeldung
 * als Beleg -- und fragt am Ende des Anrufs dort nach.
 *
 * Jeder Rueckruf von Twilio hat seine eigenen Felder und fasst nichts an, was
 * schon steht: Die Meldungen kommen nicht in einer verlaesslichen Reihenfolge
 * und werden bei Zweifeln wiederholt.
 */
class CallService
{
    /** Grund, mit dem ein bei Twilio gescheiterter Versuch verworfen wird. */
    public const IGNORE_TWILIO_ERROR = 'twilio_error';

    /** Grund, mit dem ein Versuch ohne Abnehmen des Kaeufers verworfen wird. */
    public const IGNORE_BUYER_NO_ANSWER = 'buyer_no_answer';

    /**
     * So lange gilt ein Versuch ohne Schlussmeldung als laufend. Danach ist
     * die Meldung von Twilio verloren gegangen und der Lead wieder anrufbar.
     */
    private const STALE_ATTEMPT_SECONDS = 3600;

    /** Ansagesprache der Bridge. Die Leads sind deutschsprachig. */
    private const SAY_LANGUAGE = 'de-DE';

    public function __construct(
        private readonly OutboundCallClient $client,
        private readonly CallerIdService $callerIds,
        private readonly AttemptClassifier $classifier,
        private readonly LeadResolver $resolver,
    ) {}

    /**
     * Startet den Anruf und legt den Versuch an.
     *
     * Alles, was den Anruf verhindert, wird vor dem Waehlen geprueft: Ein
     * Anruf, der ohnehin nicht zaehlt, kostet sonst Geld und laesst beim Lead
     * das Telefon klingeln. Die Bewertung prueft den Mindestabstand spaeter
     * noch einmal (FB-083) -- hier geht es nur darum, ihn gar nicht erst zu
     * verletzen.
     *
     * @throws CallNotPossible
     * @throws OutboundCallFailed
     */
    public function start(LeadPurchase $purchase, User $user, Tenant $tenant): CallAttempt
    {
        if ((int) $purchase->buyer_tenant_id !== (int) $tenant->getKey()) {
            throw CallNotPossible::because('call.attempt.errors.foreign_purchase');
        }

        $platformNumber = $this->platformNumber();

        if ($platformNumber === '') {
            throw CallNotPossible::because('call.attempt.errors.not_configured');
        }

        $callerId = $this->callerIds->forUser($user);

        if ($callerId === null || ! $callerId->isUsableAsCallerId()) {
            throw CallNotPossible::because('call.attempt.errors.caller_id_missing');
        }

        $lead = $purchase->lead;

        // Ist die Erreichbarkeit entschieden, aendert kein weiterer Anruf
        // etwas daran -- weder an der Abrechnung noch an der Gutschrift.
        if (! $lead->isOpen()) {
            throw CallNotPossible::because('call.attempt.errors.lead_resolved');
        }

        // Ob der Anrufende ueberhaupt an diesen Lead darf, entscheidet der
        // LeadContactResolver anhand des Kaufbelegs (FB-032): Wer nur die
        // verdeckte Fassung sieht, hat den Lead nicht gekauft.
        $contact = $lead->contactFor($user);

        if ($contact->masked) {
            throw CallNotPossible::because('call.attempt.errors.lead_number_missing');
        }

        // Gewaehlt wird die echte Nummer aus dem Lead und nicht die aus dem
        // Kontakt: Vor der Abrechnung liefert der Resolver dem Kaeufer nur die
        // verkuerzte Fassung (FB-085). Dieser Wert bleibt serverseitig -- er
        // wandert in den Versuch und von dort in die Bridge, nie in den
        // Browser des Kaeufers.
        $leadNumber = $lead->phone_e164;

        if ($leadNumber === null || trim($leadNumber) === '') {
            throw CallNotPossible::because('call.attempt.errors.lead_number_missing');
        }

        $this->guardRetryInterval($purchase);

        $attempt = $this->openAttempt($purchase, $user, $tenant, $callerId->phone_number, $leadNumber);

        // Erst speichern, dann waehlen: Die Rueckrufadressen tragen die Kennung
        // des Versuchs, und Twilio kann schneller zurueckrufen, als dieser
        // Aufruf antwortet.
        try {
            $sid = $this->client->call(
                $callerId->phone_number,
                $platformNumber,
                route('twilio.calls.bridge', ['attempt' => $attempt->uuid]),
                route('twilio.calls.status', ['attempt' => $attempt->uuid]),
            );
        } catch (Throwable $exception) {
            // Ein Versuch, der nie zustande kam, darf weder gegen den Kaeufer
            // zaehlen noch als offener Anruf den naechsten Klick blockieren.
            $attempt->forceFill([
                'status' => CallAttemptStatus::FAILED,
                'outcome' => CallAttemptOutcome::FAILED_IGNORED,
                'ignore_reason' => self::IGNORE_TWILIO_ERROR,
                'ended_at' => now(),
            ])->save();

            throw $exception;
        }

        $attempt->forceFill(['provider_call_sid' => $sid])->save();

        return $attempt;
    }

    /**
     * Die Portal-Nummer. Kanonisch steht sie in config('twilio.from_number');
     * der alte Ort aus SaaSykit bleibt als Rueckfallebene, weil beide
     * denselben Env-Schluessel lesen.
     */
    private function platformNumber(): string
    {
        $number = (string) config('twilio.from_number');

        return $number !== '' ? $number : (string) config('services.twilio.from');
    }

    /**
     * Mindestabstand zwischen zwei gueltigen Versuchen.
     *
     * Gezaehlt wird ab dem letzten gueltigen Fehlversuch desselben Kaeufers --
     * verworfene Versuche halten die Uhr nicht an.
     *
     * @throws CallNotPossible
     */
    private function guardRetryInterval(LeadPurchase $purchase): void
    {
        $hours = (int) config('lead_calls.retry_min_hours');

        if ($hours <= 0) {
            return;
        }

        /** @var CallAttempt|null $last */
        $last = $purchase->callAttempts()
            ->where('outcome', CallAttemptOutcome::FAILED_VALID)
            ->latest('started_at')
            ->first();

        if ($last === null || ! $last->started_at instanceof Carbon) {
            return;
        }

        $nextAllowedAt = $last->started_at->copy()->addHours($hours);

        if ($nextAllowedAt->isFuture()) {
            throw CallNotPossible::because('call.attempt.errors.too_soon', [
                'time' => $nextAllowedAt->timezone(config('app.timezone'))->format('H:i'),
            ]);
        }
    }

    /**
     * Legt den Versuch an -- aber nur, wenn zu diesem Lead gerade keiner
     * laeuft.
     *
     * Pruefung und Anlage gehoeren zusammen, sonst legen zwei schnelle Klicks
     * zwei Anrufe an. Die Sperre haengt am Lead und nicht am Kaufbeleg: Bei
     * einem geteilten Lead (FB-055) soll den Endkunden nicht gleichzeitig aus
     * zwei Richtungen das Telefon klingeln.
     *
     * @throws CallNotPossible
     */
    private function openAttempt(
        LeadPurchase $purchase,
        User $user,
        Tenant $tenant,
        string $callerNumber,
        string $leadNumber,
    ): CallAttempt {
        $lock = Cache::lock('lead-call:'.$purchase->lead_id, 10);

        if (! $lock->get()) {
            throw CallNotPossible::alreadyRunning();
        }

        try {
            // Bleibt die Schlussmeldung von Twilio einmal aus, haengt der
            // Versuch fuer immer offen und der Lead waere nie wieder anrufbar.
            // Deshalb gilt nur als laufend, was gerade erst begonnen hat.
            $running = CallAttempt::query()
                ->withoutGlobalScopes()
                ->where('lead_id', $purchase->lead_id)
                ->whereNull('ended_at')
                ->where('started_at', '>=', now()->subSeconds(self::STALE_ATTEMPT_SECONDS))
                ->exists();

            if ($running) {
                throw CallNotPossible::alreadyRunning();
            }

            $attempt = new CallAttempt;

            $attempt->forceFill([
                'tenant_id' => $tenant->getKey(),
                'lead_purchase_id' => $purchase->getKey(),
                'lead_id' => $purchase->lead_id,
                'user_id' => $user->getKey(),
                'caller_number' => $callerNumber,
                'lead_number' => $leadNumber,
                'status' => CallAttemptStatus::QUEUED,
                'started_at' => now(),
            ])->save();

            return $attempt;
        } finally {
            $lock->release();
        }
    }

    /**
     * Die Anweisung, die Twilio abholt, sobald der Kaeufer abnimmt: durchstellen
     * zum Lead (FB-081).
     *
     * Hier -- und nur hier -- taucht die Rufnummer des Leads auf. Sie wird
     * serverseitig aus dem Lead gelesen und geht nie an einen Browser.
     *
     * Als Rufnummernanzeige erscheint die bestaetigte Rufnummer des anrufenden
     * Mitarbeiters. So entschieden von Enes am 10.09.2026 und am 11.09.2026
     * bestaetigt: Ein Endkunde soll zurueckrufen koennen und dabei den
     * Menschen erreichen, der ihn angerufen hat. Das hebt Entscheidung 1 aus
     * Ticket #7 (Portal-Nummer) wieder auf.
     *
     * Genau dafuer wird die Nummer ueber FB-080 bei Twilio bestaetigt -- eine
     * fremde Nummer laesst Twilio als Anzeige nicht zu. Fehlt sie wider
     * Erwarten, faellt die Anzeige auf die Portal-Nummer zurueck, statt den
     * Anruf scheitern zu lassen.
     */
    public function bridgeInstruction(CallAttempt $attempt): VoiceResponse
    {
        $response = new VoiceResponse;

        $lead = $attempt->lead;
        $leadNumber = (string) ($lead->phone_e164 ?? '');

        // Zwischen Klick und Bridge koennen Minuten liegen. Ist die
        // Erreichbarkeit inzwischen entschieden, wird nicht mehr gewaehlt --
        // ein Anruf, der nichts mehr aendert, soll beim Lead nicht klingeln.
        if (! $lead->isOpen() || $leadNumber === '') {
            $response->say(__('call.bridge.closed'), ['language' => self::SAY_LANGUAGE]);
            $response->hangup();

            return $response;
        }

        $response->say(__('call.bridge.connecting'), ['language' => self::SAY_LANGUAGE]);

        $dial = $response->dial('', [
            'callerId' => $attempt->caller_number !== '' ? $attempt->caller_number : $this->platformNumber(),
            'timeout' => (int) config('lead_calls.lead_ring_timeout'),
            // Klingelzeit beim Lead zaehlt sonst als Gespraechszeit des
            // Kaeufers -- und damit als erreicht.
            'answerOnBridge' => true,
            'action' => route('twilio.calls.dial-done', ['attempt' => $attempt->uuid]),
            'method' => 'POST',
        ]);

        $dial->number($leadNumber, [
            // Die Anrufbeantworter-Erkennung wird von Anfang an mitgeschrieben,
            // auch wenn erst FB-083 entscheidet, ob eine Mailbox als erreicht
            // zaehlt (Ticket #12).
            'machineDetection' => (string) config('lead_calls.machine_detection'),
            'amdStatusCallback' => route('twilio.calls.machine-detection', ['attempt' => $attempt->uuid]),
            'amdStatusCallbackMethod' => 'POST',
            'statusCallback' => route('twilio.calls.leg-status', ['attempt' => $attempt->uuid]),
            'statusCallbackMethod' => 'POST',
            'statusCallbackEvent' => 'initiated ringing answered completed',
        ]);

        return $response;
    }

    /**
     * Standmeldung zum Kaeufer-Bein (FB-082).
     *
     * Beschreibt allein den Anruf beim Mitarbeiter. Sobald durchgestellt ist,
     * zaehlt fuer den Versuch nur noch das Lead-Bein -- diese Meldung fasst
     * dann nichts mehr an, weil Twilio die Rueckrufe nicht in einer festen
     * Reihenfolge zustellt.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordBuyerStatus(CallAttempt $attempt, array $payload): CallAttempt
    {
        $status = CallAttemptStatus::fromProvider($this->stringFrom($payload, 'CallStatus') ?? '');

        $bridged = $attempt->provider_dial_sid !== null;

        $attributes = [];

        if (! $bridged && ! $attempt->status->isFinished()) {
            $attributes['status'] = $status;
        }

        // Der Kaeufer selbst hat nicht abgenommen: Dann hat es beim Lead nie
        // geklingelt. Ein solcher Versuch belegt nichts ueber dessen
        // Erreichbarkeit und darf deshalb nicht gegen ihn zaehlen.
        $buyerMissed = ! $bridged && in_array($status, [
            CallAttemptStatus::NO_ANSWER,
            CallAttemptStatus::BUSY,
            CallAttemptStatus::FAILED,
            CallAttemptStatus::CANCELED,
        ], true);

        if ($attempt->ended_at === null && ($buyerMissed || $status->isFinished())) {
            $attributes['ended_at'] = now();
        }

        if ($buyerMissed && $attempt->outcome === null) {
            $attributes['outcome'] = CallAttemptOutcome::FAILED_IGNORED;
            $attributes['ignore_reason'] = self::IGNORE_BUYER_NO_ANSWER;

            // In diesem Fall folgt kein Dial-Rueckruf mehr. Damit der Versuch
            // trotzdem einen Beleg hat, bleibt die Rohmeldung hier stehen.
            if ($attempt->provider_payload === null) {
                $attributes['provider_payload'] = $payload;
            }
        }

        return $this->apply($attempt, $attributes);
    }

    /**
     * Standmeldung zum Lead-Bein (FB-082).
     *
     * Setzt nur, was noch offen ist: die Kennung des durchgestellten Anrufs,
     * den Zeitpunkt des Abnehmens und den des Auflegens. Eine doppelt
     * zugestellte Meldung aendert damit nichts.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordLegStatus(CallAttempt $attempt, array $payload): CallAttempt
    {
        $status = CallAttemptStatus::fromProvider($this->stringFrom($payload, 'CallStatus') ?? '');

        $attributes = [];

        $sid = $this->stringFrom($payload, 'CallSid');

        if ($sid !== null && $sid !== $attempt->provider_call_sid && $attempt->provider_dial_sid === null) {
            $attributes['provider_dial_sid'] = $sid;
        }

        if (! $attempt->status->isFinished()) {
            $attributes['status'] = $status;
        }

        if ($status === CallAttemptStatus::IN_PROGRESS && $attempt->answered_at === null) {
            $attributes['answered_at'] = now();
        }

        if ($status === CallAttemptStatus::COMPLETED && $attempt->ended_at === null) {
            $attributes['ended_at'] = now();
        }

        return $this->apply($attempt, $attributes);
    }

    /**
     * Ergebnis des Dial-Verbs (FB-082) -- die abschliessende Meldung.
     *
     * Sie traegt Stand und Dauer des Anrufs beim Lead und ist damit die
     * einzige Grundlage der Abrechnung. Nur hier wird bewertet und ueber die
     * Erreichbarkeit entschieden, und zwar sofort: Twilio wartet auf die
     * Antwort, alles Langsame (Erinnerungen, Post) gehoert in die Queue.
     *
     * Eine zweite Zustellung derselben Meldung laeuft ins Leere -- das
     * Ergebnis steht bereits fest, und Bewertung wie Entscheidung duerfen
     * nicht ein zweites Mal laufen.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordDialResult(CallAttempt $attempt, array $payload): CallAttempt
    {
        $dialStatus = $this->stringFrom($payload, 'DialCallStatus');

        if ($attempt->dial_status !== null || $dialStatus === null) {
            return $attempt;
        }

        $attributes = [
            'dial_status' => $dialStatus,
            // Der Stand des Lead-Beins ist der Stand des Versuchs: Was das
            // Telefon des Mitarbeiters gemeldet hat, ist damit ueberholt.
            'status' => CallAttemptStatus::fromProvider($dialStatus),
        ];

        $duration = $this->stringFrom($payload, 'DialCallDuration');

        if ($duration !== null && $attempt->duration_seconds === null) {
            $attributes['duration_seconds'] = max(0, (int) $duration);
        }

        $dialSid = $this->stringFrom($payload, 'DialCallSid');

        if ($dialSid !== null && $attempt->provider_dial_sid === null) {
            $attributes['provider_dial_sid'] = $dialSid;
        }

        if ($attempt->provider_payload === null) {
            $attributes['provider_payload'] = $payload;
        }

        if ($attempt->ended_at === null) {
            $attributes['ended_at'] = now();
        }

        $attempt = $this->apply($attempt, $attributes);

        $attempt = $this->classifier->classify($attempt);

        $this->resolver->resolveAfter($attempt);

        return $attempt;
    }

    /**
     * Die Antwort auf den Dial-Rueckruf: auflegen.
     *
     * Ohne sie wuerde Twilio den Anruf beim Kaeufer weiterlaufen lassen und
     * die naechste Anweisung abwarten -- es gibt keine.
     */
    public function hangUpInstruction(): VoiceResponse
    {
        $response = new VoiceResponse;

        $response->hangup();

        return $response;
    }

    /**
     * Schreibt genau die Felder, die der jeweilige Rueckruf verantwortet.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function apply(CallAttempt $attempt, array $attributes): CallAttempt
    {
        if ($attributes === []) {
            return $attempt;
        }

        $attempt->forceFill($attributes)->save();

        return $attempt;
    }

    /**
     * Ergebnis der Anrufbeantworter-Erkennung. Wird nur gespeichert, nicht
     * gedeutet.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordMachineDetection(CallAttempt $attempt, array $payload): CallAttempt
    {
        $answeredBy = $this->stringFrom($payload, 'AnsweredBy');

        // Zweite Zustellung derselben Meldung: nichts zu schreiben, und vor
        // allem nicht ein zweites Mal auflegen.
        if ($answeredBy === null || $answeredBy === $attempt->answered_by) {
            return $attempt;
        }

        $attempt->forceFill(['answered_by' => $answeredBy])->save();

        // Hat ein Anrufbeantworter abgenommen, wird das Lead-Bein sofort
        // aufgelegt: Sonst laeuft die Ansage weiter, der Kaeufer haengt in der
        // Leitung und die Gespraechsdauer waechst in den Bereich, ab dem ein
        // Versuch als erreicht gilt.
        if (str_starts_with($answeredBy, 'machine')) {
            $this->hangUpLeadLeg($attempt, $payload);
        }

        return $attempt;
    }

    /**
     * Legt das Lead-Bein auf. Scheitert das bei Twilio, bleibt es dabei -- der
     * Rueckruf darf daran nicht scheitern, sonst wiederholt Twilio ihn.
     *
     * @param  array<string, mixed>  $payload
     */
    private function hangUpLeadLeg(CallAttempt $attempt, array $payload): void
    {
        $sid = $this->stringFrom($payload, 'CallSid') ?? $attempt->provider_dial_sid;

        if ($sid === null || $sid === $attempt->provider_call_sid) {
            return;
        }

        try {
            $this->client->hangUp($sid);
        } catch (Throwable) {
            // Nichts zu tun: Der Anruf endet ohnehin spaetestens mit dem
            // Auflegen des Kaeufers.
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringFrom(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
