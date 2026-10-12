<?php

declare(strict_types=1);

namespace App\Services\Phone;

use App\Constants\PhoneVerificationChannel;
use App\Constants\PhoneVerificationStatus;
use App\Events\Phone\PhoneVerified;
use App\Events\User\UserPhoneVerified;
use App\Exceptions\PhoneNumberNotRecognizedException;
use App\Exceptions\PhoneNumberTakenException;
use App\Exceptions\PhoneVerificationThrottledException;
use App\Models\PhoneVerificationAttempt;
use App\Models\User;
use App\Services\Twilio\VerifyClient;
use App\Services\Twilio\VerifyRequestFailed;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Die Rufnummernbestaetigung eines Nutzers (LP-ONBOARD-004).
 *
 * Der einzige Weg, auf dem `users.phone_number_verified_at` gesetzt wird --
 * ausser der aelteren SMS-Verifikation von SaaSykit, die dieser Dienst
 * ersetzt. Das ist keine Ordnungsfrage: An der bestaetigten Nummer haengt die
 * Rufnummernanzeige des Click-to-Call (FB-080/FB-081). Wer sie ohne Nachweis
 * setzen koennte, koennte mit einer fremden Nummer bei Leads anrufen.
 *
 * **Wir halten keine Codes.** Twilio Verify erzeugt sie, verschickt sie und
 * prueft sie. Hier liegt nur das Protokoll (`phone_verification_attempts`) --
 * fuer die Rate-Limits und fuer den Support. Daraus folgt, dass check() den
 * Code nicht selbst vergleichen kann: Es fragt Twilio.
 *
 * **Drei Grenzen, drei Gruende.** Anforderungen haengen an der Nummer, denn
 * jede kostet Geld und klingelt bei einem Menschen -- wer sich zehn Konten
 * anlegt, soll eine fremde Nummer nicht zehnmal so oft anrufen lassen koennen.
 * Pruefungen haengen an der Sitzung, denn sie sind der Rateversuch. Und der
 * erneute Versand hat eine Sperrfrist, weil die SMS unterwegs sein kann,
 * waehrend der Nutzer schon wieder drueckt.
 *
 * **Die Nummer wandert erst bei Erfolg an den Nutzer.** Vorher steht sie nur
 * im Versuch. Sonst stuende an einem Konto eine Nummer, die niemand bestaetigt
 * hat, und die Pruefung "gehoert schon einem anderen" wuerde fremde Nummern
 * blockieren, nur weil jemand sie eingetippt hat.
 */
class PhoneVerificationService
{
    public function __construct(
        private readonly VerifyClient $client,
        private readonly PhoneNumberUtil $phoneNumbers,
        private readonly Request $request,
    ) {}

    /**
     * Fordert einen Code an. Twilio schickt ihn per SMS oder sagt ihn im
     * Anruf an.
     *
     * Der Rueckgabewert ist der protokollierte Versuch -- die Oberflaeche
     * braucht daraus die Nummer in E.164 (zum Anzeigen) und den Kanal.
     *
     * @param  string  $channel  'sms' oder 'call' (App\Constants\PhoneVerificationChannel)
     *
     * @throws PhoneNumberNotRecognizedException wenn die Eingabe keine waehlbare Nummer ist.
     * @throws PhoneNumberTakenException wenn die Nummer schon bei einem anderen Konto bestaetigt ist.
     * @throws PhoneVerificationThrottledException wenn ein Limit erreicht ist.
     * @throws VerifyRequestFailed wenn Twilio die Anforderung ablehnt.
     */
    public function start(User $user, string $phone, string $channel = 'sms'): PhoneVerificationAttempt
    {
        $phoneE164 = $this->normalize($phone);
        $verificationChannel = $this->resolveChannel($channel);

        $this->guardAgainstForeignClaim($user, $phoneE164);
        $this->guardAgainstMissingSmsAttempt($user, $phoneE164, $verificationChannel);
        $this->guardResendWindow($user, $phoneE164);
        $this->guardRequestLimit($phoneE164);

        try {
            $sid = $this->client->startVerification($phoneE164, $verificationChannel);
        } catch (VerifyRequestFailed $exception) {
            // Es ging kein Code raus. Der Versuch wird protokolliert, zaehlt
            // aber nicht gegen das Limit -- sonst sperrte eine Stoerung bei
            // Twilio den Nutzer fuer eine Stunde aus.
            $this->recordAttempt($user, $phoneE164, $verificationChannel, null, PhoneVerificationStatus::FAILED);

            throw $exception;
        }

        return $this->recordAttempt($user, $phoneE164, $verificationChannel, $sid, PhoneVerificationStatus::PENDING);
    }

    /**
     * Prueft den eingegebenen Code gegen den offenen Versuch des Nutzers.
     *
     * False heisst immer dasselbe nach aussen: falsch, abgelaufen oder es gibt
     * keinen offenen Versuch. Die Unterscheidung waere ein Hinweis fuers Raten.
     *
     * @throws PhoneVerificationThrottledException wenn in dieser Sitzung zu oft geprueft wurde.
     * @throws PhoneNumberTakenException wenn die Nummer inzwischen bei einem anderen Konto bestaetigt wurde.
     */
    public function check(User $user, string $code): bool
    {
        $this->guardCheckLimit($user);

        $attempt = $this->pendingAttempt($user);

        if (! $attempt instanceof PhoneVerificationAttempt) {
            return false;
        }

        // Noch einmal, kurz vor dem Festschreiben: Zwischen Anforderung und
        // Eingabe des Codes koennen Minuten liegen, und in dieser Zeit kann
        // ein anderes Konto dieselbe Nummer bestaetigt haben.
        $this->guardAgainstForeignClaim($user, $attempt->phone_e164);

        if (! $this->client->checkVerification($attempt->phone_e164, trim($code))) {
            return false;
        }

        $this->markVerified($user, $attempt);

        return true;
    }

    /**
     * Ab wann darf der Nutzer wieder "Code senden" druecken?
     *
     * Null heisst sofort -- es gibt keinen Versuch oder die Sperrfrist ist
     * abgelaufen. Die Oberflaeche rechnet daraus ihren Zaehler.
     */
    public function resendAllowedAt(User $user): ?Carbon
    {
        $last = $this->lastCountingAttempt($user);

        if (! $last instanceof PhoneVerificationAttempt || $last->created_at === null) {
            return null;
        }

        $allowedAt = $last->created_at->copy()->addSeconds($this->resendAfterSeconds());

        return $allowedAt->isFuture() ? $allowedAt : null;
    }

    /**
     * Darf der Ausweg "Code per Anruf" angeboten werden?
     *
     * Erst nach einem SMS-Versuch fuer dieselbe Nummer. Der Anruf ist teurer
     * und soll der Ausweg bleiben, nicht der bequemere Weg.
     */
    public function callChannelAvailable(User $user, string $phone): bool
    {
        try {
            $phoneE164 = $this->normalize($phone);
        } catch (PhoneNumberNotRecognizedException) {
            return false;
        }

        return $this->hasSmsAttempt($user, $phoneE164);
    }

    /**
     * Der offene Versuch eines Nutzers -- die Nummer, um die es gerade geht.
     *
     * Oeffentlich, weil der Code-Screen (LP-ONBOARD-005) die Nummer anzeigt,
     * an die der Code ging.
     */
    public function pendingAttempt(User $user): ?PhoneVerificationAttempt
    {
        return PhoneVerificationAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('status', PhoneVerificationStatus::PENDING)
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    /**
     * Nimmt die Anforderungssperre einer Nummer zurueck (LP-ONBOARD-022).
     *
     * Der Fall: Der Nutzer hat sein Fenster leergedrueckt ("3 Anforderungen je
     * Nummer je Stunde") und ruft genau deshalb den Support an. Ohne diesen
     * Weg stuende das Konto bis zum Ablauf des Fensters, auch nachdem der
     * Support alles andere erledigt hat.
     *
     * Die Versuche werden nicht geloescht, sondern mit `cleared_at` markiert:
     * Sie zaehlen nicht mehr gegen ein Limit, bleiben aber im Protokoll
     * lesbar. Mitgenommen werden alle offenen Versuche im Fenster zu den
     * Nummern, die dieser Nutzer versucht hat -- das Limit haengt an der
     * Nummer, nicht am Konto, also muss die Nummer frei werden.
     *
     * Damit ist auch die Sperrfrist des erneuten Versands weg, denn sie liest
     * denselben letzten zaehlenden Versuch.
     *
     * NICHT zurueckgenommen wird das Pruefbudget (`checks_per_session`): Es
     * haengt an der Sitzung des Nutzers, die von hier aus nicht erreichbar
     * ist, und es laeuft nach `checks_window_minutes` von selbst ab.
     *
     * @return int Anzahl der freigegebenen Versuche.
     */
    public function releaseRequestLimit(User $user): int
    {
        $windowMinutes = (int) config('phone_verification.limits.requests_window_minutes', 60);
        $since = now()->subMinutes(max(1, $windowMinutes));

        $phoneNumbers = PhoneVerificationAttempt::query()
            ->counting()
            ->where('user_id', $user->getKey())
            ->where('created_at', '>=', $since)
            ->pluck('phone_e164')
            ->push($user->phone_e164)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($phoneNumbers === []) {
            return 0;
        }

        return PhoneVerificationAttempt::query()
            ->counting()
            ->whereIn('phone_e164', $phoneNumbers)
            ->where('created_at', '>=', $since)
            ->update(['cleared_at' => now()]);
    }

    /**
     * Zaehlende Versuche einer Nummer im laufenden Fenster.
     *
     * Der Admin entscheidet daran, ob der Knopf "Sperre aufheben" ueberhaupt
     * etwas zu tun haette (LP-ONBOARD-022).
     */
    public function countingRequestsInWindow(User $user): int
    {
        $windowMinutes = (int) config('phone_verification.limits.requests_window_minutes', 60);

        return PhoneVerificationAttempt::query()
            ->counting()
            ->where('user_id', $user->getKey())
            ->where('created_at', '>=', now()->subMinutes(max(1, $windowMinutes)))
            ->count();
    }

    /**
     * Eingabe zu E.164.
     *
     * Gelesen wird gegen `phone_verification.region` als Standardvorwahl, eine
     * Nummer mit eigener Laendervorwahl muss aus `phone_verification.regions`
     * kommen. Festnetz ist erlaubt -- ein Handwerksbetrieb erreicht seine
     * Leads ueber die Werkstattnummer.
     *
     * @throws PhoneNumberNotRecognizedException
     */
    public function normalize(string $input): string
    {
        $region = (string) config('phone_verification.region', 'DE');

        try {
            $parsed = $this->phoneNumbers->parse(trim($input), $region);
        } catch (NumberParseException) {
            throw PhoneNumberNotRecognizedException::make();
        }

        if (! $this->phoneNumbers->isValidNumber($parsed)) {
            throw PhoneNumberNotRecognizedException::make();
        }

        /** @var list<string> $allowed */
        $allowed = (array) config('phone_verification.regions', []);
        $numberRegion = $this->phoneNumbers->getRegionCodeForNumber($parsed);

        if ($allowed !== [] && ! in_array($numberRegion, $allowed, strict: true)) {
            throw PhoneNumberNotRecognizedException::make();
        }

        return $this->phoneNumbers->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * Schreibt die Bestaetigung fest und loest die Folgen aus.
     */
    private function markVerified(User $user, PhoneVerificationAttempt $attempt): void
    {
        $attempt->forceFill(['status' => PhoneVerificationStatus::APPROVED])->save();

        $user->forceFill([
            'phone_e164' => $attempt->phone_e164,
            // Die Eingabeform bleibt stehen, falls sie schon da war -- sonst
            // steht am Konto keine Nummer, die ein Mensch wiedererkennt.
            'phone_number' => $user->phone_number ?: $attempt->phone_e164,
            'phone_number_verified_at' => now(),
            'phone_verification_channel' => $attempt->channel,
        ])->save();

        // Die Sitzung hat ihr Pruefbudget verbraucht, aber erfolgreich: Ein
        // zweiter Nutzer am selben Rechner soll nicht auf dem Restzaehler
        // sitzen.
        RateLimiter::clear($this->checkLimiterKey($user));

        event(new PhoneVerified($user, $attempt->channel));

        // Das aeltere Ereignis aus SaaSykit: An ihm haengt die Freischaltung
        // von Abos, die auf die Verifizierung warten.
        UserPhoneVerified::dispatch($user);
    }

    /**
     * @throws PhoneNumberTakenException
     */
    private function guardAgainstForeignClaim(User $user, string $phoneE164): void
    {
        $taken = User::query()
            ->where('phone_e164', $phoneE164)
            ->whereNotNull('phone_number_verified_at')
            ->whereKeyNot($user->getKey())
            ->exists();

        if ($taken) {
            throw PhoneNumberTakenException::make();
        }
    }

    /**
     * Der Anruf-Ausweg erst nach einem SMS-Versuch fuer dieselbe Nummer.
     *
     * @throws PhoneVerificationThrottledException
     */
    private function guardAgainstMissingSmsAttempt(User $user, string $phoneE164, PhoneVerificationChannel $channel): void
    {
        if (! $channel->requiresPreviousSmsAttempt()) {
            return;
        }

        if ($this->hasSmsAttempt($user, $phoneE164)) {
            return;
        }

        // Derselbe Ausnahmetyp wie bei den Limits: Fuer den Aufrufer ist es
        // derselbe Fall -- dieser Knopf darf jetzt nicht benutzt werden.
        throw PhoneVerificationThrottledException::callRequiresSmsFirst();
    }

    private function hasSmsAttempt(User $user, string $phoneE164): bool
    {
        return PhoneVerificationAttempt::query()
            ->counting()
            ->where('user_id', $user->getKey())
            ->where('phone_e164', $phoneE164)
            ->where('channel', PhoneVerificationChannel::SMS)
            ->exists();
    }

    /**
     * @throws PhoneVerificationThrottledException
     */
    private function guardResendWindow(User $user, string $phoneE164): void
    {
        $last = PhoneVerificationAttempt::query()
            ->counting()
            ->where('user_id', $user->getKey())
            ->where('phone_e164', $phoneE164)
            ->latest('created_at')
            ->latest('id')
            ->first();

        if (! $last instanceof PhoneVerificationAttempt || $last->created_at === null) {
            return;
        }

        $allowedAt = $last->created_at->copy()->addSeconds($this->resendAfterSeconds());

        if ($allowedAt->isFuture()) {
            throw PhoneVerificationThrottledException::resendTooEarly(
                max(1, (int) ceil(now()->diffInSeconds($allowedAt, absolute: true))),
            );
        }
    }

    /**
     * Anforderungen je Nummer je Zeitfenster.
     *
     * Die Wartezeit ist der Ablauf des aeltesten Versuchs im Fenster und nicht
     * das ganze Fenster: Wer vor 59 Minuten dreimal gedrueckt hat, darf in
     * einer Minute wieder.
     *
     * @throws PhoneVerificationThrottledException
     */
    private function guardRequestLimit(string $phoneE164): void
    {
        $limit = (int) config('phone_verification.limits.requests_per_phone', 3);
        $windowMinutes = (int) config('phone_verification.limits.requests_window_minutes', 60);

        if ($limit <= 0) {
            return;
        }

        $since = now()->subMinutes($windowMinutes);

        $attempts = PhoneVerificationAttempt::query()
            ->counting()
            ->where('phone_e164', $phoneE164)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->get();

        if ($attempts->count() < $limit) {
            return;
        }

        $oldest = $attempts->first();
        $freeAt = $oldest?->created_at?->copy()->addMinutes($windowMinutes) ?? now()->addMinutes($windowMinutes);

        throw PhoneVerificationThrottledException::tooManyRequests(
            max(1, (int) ceil(now()->diffInSeconds($freeAt, absolute: true))),
        );
    }

    /**
     * Pruefungen je Sitzung je Zeitfenster.
     *
     * Ueber den RateLimiter und nicht ueber die Protokolltabelle: Eine
     * fehlgeschlagene Pruefung legt dort bewusst keine Zeile an -- sonst
     * koennte ein Rateversuch das Protokoll der Nummer fluten.
     *
     * @throws PhoneVerificationThrottledException
     */
    private function guardCheckLimit(User $user): void
    {
        $limit = (int) config('phone_verification.limits.checks_per_session', 5);
        $windowMinutes = (int) config('phone_verification.limits.checks_window_minutes', 10);

        if ($limit <= 0) {
            return;
        }

        $key = $this->checkLimiterKey($user);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw PhoneVerificationThrottledException::tooManyChecks(
                max(1, RateLimiter::availableIn($key)),
            );
        }

        RateLimiter::hit($key, $windowMinutes * 60);
    }

    /**
     * Der Schluessel des Pruefzaehlers: die Sitzung.
     *
     * Ohne Sitzung (Konsole, API) faellt er auf den Nutzer zurueck -- ein
     * Zaehler ohne Schluessel waere einer fuer alle.
     */
    private function checkLimiterKey(User $user): string
    {
        $session = $this->request->hasSession()
            ? $this->request->session()->getId()
            : 'user-'.$user->getKey();

        return 'phone-verification:check:'.$session;
    }

    private function lastCountingAttempt(User $user): ?PhoneVerificationAttempt
    {
        return PhoneVerificationAttempt::query()
            ->counting()
            ->where('user_id', $user->getKey())
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    private function recordAttempt(
        User $user,
        string $phoneE164,
        PhoneVerificationChannel $channel,
        ?string $sid,
        PhoneVerificationStatus $status,
    ): PhoneVerificationAttempt {
        return PhoneVerificationAttempt::query()->create([
            'user_id' => $user->getKey(),
            'phone_e164' => $phoneE164,
            'channel' => $channel,
            'verification_sid' => $sid,
            'status' => $status,
        ]);
    }

    private function resolveChannel(string $channel): PhoneVerificationChannel
    {
        return PhoneVerificationChannel::tryFrom($channel) ?? PhoneVerificationChannel::default();
    }

    private function resendAfterSeconds(): int
    {
        return max(0, (int) config('phone_verification.limits.resend_after_seconds', 60));
    }
}
