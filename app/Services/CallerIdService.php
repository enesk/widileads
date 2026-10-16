<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\AuditAction;
use App\Constants\CallerIdStatus;
use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Twilio\CallerIdValidationClient;
use App\Services\Twilio\CallerIdValidationFailed;
use App\Services\Twilio\CallerIdValidationThrottled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Fuehrt die Bestaetigung der Rufnummer eines Kaeufer-Mitarbeiters (FB-080).
 *
 * Der Stand einer Nummer entsteht ausschliesslich hier. Zwei Wege fuehren
 * hinein: der Mitarbeiter fordert die Bestaetigung an, und Twilio meldet ihr
 * Ergebnis zurueck. Ein dritter Weg -- etwa ein Formular, das `verified`
 * mitschickt -- darf es nicht geben, sonst ist die Bestaetigung wertlos.
 *
 * Gespeichert wird immer E.164. Eine Nummer, die Twilio nicht waehlen kann,
 * wird gar nicht erst angelegt.
 */
class CallerIdService
{
    public function __construct(
        private readonly CallerIdValidationClient $client,
        private readonly PhoneNumberUtil $phoneNumbers,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Die Nummer eines Benutzers, unabhaengig vom Mandantenkontext.
     *
     * Bewusst ohne den Mandanten-Scope: Der Rueckruf von Twilio und die
     * Konsole haben keinen Mandanten im Kontext, sollen die Nummer aber finden.
     */
    public function forUser(User $user): ?CallerId
    {
        return CallerId::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->first();
    }

    /**
     * Fordert die Bestaetigung an: Twilio ruft die Nummer an und sagt den Code
     * an, den der Rueckgabewert traegt.
     *
     * Eine bereits bestaetigte Nummer eines anderen Benutzers wird abgelehnt.
     * Sonst koennte ein Mitarbeiter die Nummer eines Kollegen als eigene
     * Rufnummernanzeige beanspruchen, ohne je ein Telefon in der Hand gehabt zu
     * haben.
     *
     * Lehnt Twilio die Anforderung ab, bleibt der Datensatz nicht auf
     * `pending` stehen, sondern geht auf `failed` -- es klingelt ja nichts.
     *
     * Die Begrenzung (Ticket #97) steht VOR dem Schreiben der Zeile: Eine
     * abgewiesene Anforderung darf keinen `pending`-Rest hinterlassen, auf den
     * die Oberflaeche dann wartet.
     *
     * Festnetz ist ausdruecklich erlaubt -- geprueft wird allein, ob die
     * Nummer waehlbar ist (`normalize()`). Ein Handwerksbetrieb erreicht seine
     * Leads ueber die Werkstattnummer.
     *
     * @throws InvalidArgumentException wenn die Eingabe keine waehlbare Nummer ist.
     * @throws CallerIdValidationThrottled wenn zu oft hintereinander angefordert wurde.
     * @throws CallerIdValidationFailed wenn Twilio die Anforderung ablehnt.
     */
    public function requestValidation(Tenant $tenant, User $user, string $input, string $statusCallbackUrl): CallerId
    {
        $phoneNumber = $this->normalize($input);

        $this->guardAgainstForeignClaim($phoneNumber, $user);
        $this->guardAgainstTooManyRequests($phoneNumber, $user);

        $ttl = (int) config('funnel.call.caller_id.validation_ttl_minutes');

        $callerId = DB::transaction(function () use ($tenant, $user, $phoneNumber, $ttl): CallerId {
            $callerId = $this->forUser($user) ?? new CallerId;

            $callerId->forceFill([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'phone_number' => $phoneNumber,
                'status' => CallerIdStatus::PENDING,
                'validation_sid' => null,
                'validation_code' => null,
                'requested_at' => now(),
                'verified_at' => null,
                'expires_at' => now()->addMinutes($ttl),
            ])->save();

            return $callerId;
        });

        $this->countRequest($phoneNumber, $user);

        // Erst nach dem Speichern gewaehlt: Klingelt das Telefon, muss der Stand
        // schon stehen -- der Rueckruf von Twilio kann schneller sein als die
        // Antwort auf diesen Aufruf.
        try {
            $validation = $this->client->requestValidation(
                $phoneNumber,
                $this->friendlyNameFor($user),
                $statusCallbackUrl,
            );
        } catch (CallerIdValidationFailed $exception) {
            // Ticket #38: Es klingelt kein Telefon, also darf der Stand auch
            // nicht auf "laeuft" stehen bleiben. Sonst haengt die Zeile bis zum
            // Verfall auf `pending` -- und `expireIfOverdue()` raeumt sie nur
            // auf, wenn jemand die Oberflaeche oeffnet.
            $callerId->forceFill([
                'status' => CallerIdStatus::FAILED,
                'validation_code' => null,
                'expires_at' => null,
            ])->save();

            throw $exception;
        }

        $callerId->forceFill([
            'validation_sid' => $validation->callSid,
            'validation_code' => $validation->validationCode,
        ])->save();

        $this->auditLogger->log(
            AuditAction::CALLER_ID_REQUESTED,
            $callerId,
            ['phone_number' => $phoneNumber],
            $tenant,
            $user,
        );

        return $callerId->refresh();
    }

    /**
     * Uebernimmt eine anderweitig bestaetigte Nummer als Rufnummernanzeige
     * (LP-ONBOARD-004).
     *
     * Der Weg fuer die Rufnummernbestaetigung des Onboardings: Twilio Verify
     * hat dort bereits bewiesen, dass der Nutzer die Nummer in der Hand hat.
     * Ein zweiter Bestaetigungsanruf wuerde dieselbe Tatsache ein zweites Mal
     * nachweisen und dem Kaeufer einen weiteren Abbruchgrund geben.
     *
     * Deshalb steht die Methode hier und nicht beim Aufrufer: Der Stand einer
     * Rufnummer entsteht ausschliesslich in diesem Dienst. Eine Stelle, die
     * `verified` selbst in die Tabelle schreibt, waere genau die Luecke, die
     * die Bestaetigung schliessen soll.
     *
     * **Eine vorhandene Zeile wird erneuert, wenn sie dieselbe Nummer traegt
     * und unbrauchbar ist** (`failed` oder `expired`, Ticket #49): Genau dieser
     * Nutzer wurde sonst nach der Telefonbestaetigung in einen zweiten
     * Bestaetigungsanruf fuer dieselbe Nummer geschickt -- auf einem
     * Twilio-Trial-Konto in eine Sackgasse. Der frische Verify-Nachweis sagt
     * ueber diese Nummer dasselbe, was der Anruf sagen wuerde.
     *
     * Nur dieselbe Nummer: Steht in der Zeile eine andere (etwa eine
     * Firmennummer, deren Bestaetigungsanruf scheiterte), ist das eine bewusste
     * Entscheidung des Nutzers oder des Supports. Der Nachweis gilt nur fuer
     * seine eigene Nummer und raeumt die fremde nicht weg. `pending` bleibt
     * ebenfalls stehen -- dort klingelt gerade ein Telefon.
     *
     * Null heisst: nichts getan -- der Nutzer hat schon eine nutzbare oder eine
     * andere Nummer, oder die Nummer ist bei einem anderen Nutzer bestaetigt.
     */
    public function adoptVerifiedNumber(Tenant $tenant, User $user, string $phoneNumber): ?CallerId
    {
        $normalized = $this->normalizeQuietly($phoneNumber);
        $existing = $this->forUser($user);

        if ($existing instanceof CallerId && ! $this->isRenewable($existing, $normalized)) {
            return null;
        }

        try {
            $this->guardAgainstForeignClaim($normalized, $user);
        } catch (InvalidArgumentException) {
            return null;
        }

        $isRenewal = $existing instanceof CallerId;
        $callerId = $existing ?? new CallerId;

        // Bei der Erneuerung bleiben `tenant_id` und `requested_at` stehen: Die
        // Zeile gehoert weiter dem Workspace, in dem sie angefordert wurde, und
        // wann das war, ist Teil ihrer Geschichte.
        $callerId->forceFill(array_merge($isRenewal ? [] : [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'phone_number' => $normalized,
            'requested_at' => now(),
        ], [
            'status' => CallerIdStatus::VERIFIED,
            'validation_sid' => null,
            'validation_code' => null,
            'verified_at' => now(),
            'expires_at' => null,
        ]))->save();

        $this->auditLogger->log(
            AuditAction::CALLER_ID_VERIFIED,
            $callerId,
            [
                'phone_number' => $normalized,
                'source' => $isRenewal ? 'phone_verification_renewal' : 'phone_verification',
            ],
            $tenant,
            $user,
        );

        return $callerId;
    }

    /**
     * Darf diese vorhandene Zeile vom Verify-Nachweis gehoben werden?
     *
     * Nur eine unbrauchbare Zeile mit genau dieser Nummer. Verglichen wird
     * gegen die normalisierte Fassung, weil in der Tabelle immer E.164 steht.
     */
    private function isRenewable(CallerId $callerId, string $normalized): bool
    {
        if ($callerId->phone_number !== $normalized) {
            return false;
        }

        return $callerId->status->isFailure();
    }

    /**
     * Ergebnis des Bestaetigungsanrufs, wie Twilio es zurueckmeldet.
     *
     * Zugeordnet wird ueber die Nummer: Der Rueckruf traegt keine unserer
     * Kennungen. Eine Nummer, zu der keine offene Bestaetigung liegt, wird
     * stillschweigend verworfen -- ein spaeter Rueckruf darf keinen
     * abgeschlossenen Stand mehr aendern.
     *
     * `$callStatus` ist Twilios `CallStatus` aus demselben Rueckruf. Er
     * trennt die beiden Fehlerfaelle, die sich ehrlich trennen lassen: Es hat
     * niemand abgenommen, oder der Anruf kam zustande und wurde nicht
     * bestaetigt (Ticket #97, siehe CallerIdStatus).
     */
    public function completeValidation(string $phoneNumber, bool $verified, ?string $callStatus = null): ?CallerId
    {
        $callerId = CallerId::query()
            ->withoutGlobalScopes()
            ->where('phone_number', $this->normalizeQuietly($phoneNumber))
            ->where('status', CallerIdStatus::PENDING)
            ->first();

        if (! $callerId instanceof CallerId) {
            return null;
        }

        if (! $verified) {
            $callerId->forceFill([
                'status' => $this->failureStatusFor($callStatus),
                'validation_code' => null,
                'expires_at' => null,
            ])->save();

            return $callerId;
        }

        $callerId->forceFill([
            'status' => CallerIdStatus::VERIFIED,
            'validation_code' => null,
            'verified_at' => now(),
            'expires_at' => null,
        ])->save();

        $this->auditLogger->log(
            AuditAction::CALLER_ID_VERIFIED,
            $callerId,
            ['phone_number' => $callerId->phone_number],
            $callerId->tenant,
            $callerId->user,
        );

        return $callerId;
    }

    /**
     * Setzt eine verfallene Bestaetigung auf `expired`. Aufgerufen, wenn die
     * Oberflaeche den Stand zeigt -- ein abgelaufener Code soll nicht als
     * "laeuft noch" erscheinen.
     */
    public function expireIfOverdue(CallerId $callerId): CallerId
    {
        if ($callerId->hasExpiredValidation()) {
            $callerId->forceFill([
                'status' => CallerIdStatus::EXPIRED,
                'validation_code' => null,
            ])->save();
        }

        return $callerId;
    }

    /**
     * Eingabe zu E.164, gelesen gegen die Region aus der Konfiguration.
     *
     * @throws InvalidArgumentException
     */
    public function normalize(string $input): string
    {
        $region = (string) config('funnel.call.caller_id.region');

        try {
            $parsed = $this->phoneNumbers->parse(trim($input), $region);
        } catch (NumberParseException) {
            throw new InvalidArgumentException('Keine lesbare Rufnummer.');
        }

        if (! $this->phoneNumbers->isValidNumber($parsed)) {
            throw new InvalidArgumentException('Keine waehlbare Rufnummer.');
        }

        return $this->phoneNumbers->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * Wie normalize(), gibt bei unlesbarer Eingabe aber die Eingabe zurueck --
     * fuer den Rueckruf von Twilio, der ohnehin schon E.164 liefert.
     */
    private function normalizeQuietly(string $input): string
    {
        try {
            return $this->normalize($input);
        } catch (InvalidArgumentException) {
            return trim($input);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function guardAgainstForeignClaim(string $phoneNumber, User $user): void
    {
        $taken = CallerId::query()
            ->withoutGlobalScopes()
            ->where('phone_number', $phoneNumber)
            ->where('user_id', '!=', $user->getKey())
            ->where('status', CallerIdStatus::VERIFIED)
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException('Diese Rufnummer ist bereits bestaetigt.');
        }
    }

    /**
     * Welcher Fehlerstand gehoert zu dieser Rueckmeldung?
     *
     * `no-answer`, `busy` und `canceled` heissen: Es hat niemand abgenommen.
     * `failed` heisst: Twilio kam gar nicht durch. Alles andere -- vor allem
     * `completed` ohne Bestaetigung -- heisst: Der Anruf lief, der Code wurde
     * nicht bestaetigt. Ob dahinter ein Anrufbeantworter, ein Tippfehler oder
     * ein Auflegen steckt, sagt Twilio nicht.
     */
    private function failureStatusFor(?string $callStatus): CallerIdStatus
    {
        return match (strtolower(trim((string) $callStatus))) {
            'no-answer', 'busy', 'canceled' => CallerIdStatus::NO_ANSWER,
            'failed' => CallerIdStatus::FAILED,
            default => CallerIdStatus::NOT_CONFIRMED,
        };
    }

    /**
     * Darf jetzt ein Bestaetigungsanruf ausgeloest werden? (Ticket #97)
     *
     * Drei Grenzen, in der Reihenfolge, in der sie dem Nutzer am meisten
     * sagen: die Wartezeit seit der letzten Anforderung (daraus wird der
     * Zaehler in der Oberflaeche), die Anrufe je Nummer und die je Nutzer.
     *
     * @throws CallerIdValidationThrottled
     */
    private function guardAgainstTooManyRequests(string $phoneNumber, User $user): void
    {
        $limits = (array) config('funnel.call.caller_id.limits');
        $wait = (int) ($limits['resend_after_seconds'] ?? 0);
        $existing = $this->forUser($user);

        if ($wait > 0 && $existing instanceof CallerId && $existing->requested_at !== null) {
            $elapsed = $existing->requested_at->diffInSeconds(now());

            if ($elapsed < $wait) {
                throw new CallerIdValidationThrottled((int) ceil($wait - $elapsed));
            }
        }

        foreach ($this->limiterKeys($phoneNumber, $user) as $key => $max) {
            if ($max > 0 && RateLimiter::tooManyAttempts($key, $max)) {
                throw new CallerIdValidationThrottled(RateLimiter::availableIn($key));
            }
        }
    }

    /** Zaehlt die Anforderung -- erst, wenn wirklich gewaehlt wird. */
    private function countRequest(string $phoneNumber, User $user): void
    {
        $window = (int) config('funnel.call.caller_id.limits.requests_window_minutes') * 60;

        foreach (array_keys($this->limiterKeys($phoneNumber, $user)) as $key) {
            RateLimiter::hit($key, $window);
        }
    }

    /**
     * Die Zaehler und ihre Hoechstwerte: einer an der Nummer, einer am Nutzer.
     *
     * @return array<string, int>
     */
    private function limiterKeys(string $phoneNumber, User $user): array
    {
        $limits = (array) config('funnel.call.caller_id.limits');

        return [
            'caller-id-validation:phone:'.$phoneNumber => (int) ($limits['requests_per_phone'] ?? 0),
            'caller-id-validation:user:'.$user->getKey() => (int) ($limits['requests_per_user'] ?? 0),
        ];
    }

    /**
     * Name, unter dem die Nummer im Twilio-Konto erscheint. Hilft dort, einen
     * Eintrag einem Menschen zuzuordnen.
     */
    private function friendlyNameFor(User $user): string
    {
        return trim($user->name.' ('.$user->email.')');
    }
}
