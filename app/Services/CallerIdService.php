<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\AuditAction;
use App\Constants\CallerIdStatus;
use App\Constants\PhoneVerificationChannel;
use App\Events\Phone\PhoneVerified;
use App\Events\User\UserPhoneVerified;
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
 * **Seit Ticket #97 der einzige Weg.** Twilio Verify (SMS-Code) ist abgebaut:
 * Es bewies den Besitz einer Nummer, legte im Twilio-Konto aber keine
 * Verified Caller ID an -- der Anruf beim Lead scheiterte danach mit Fehler
 * 21210 (#96). Der Bestaetigungsanruf erledigt beides in einem Vorgang, und
 * deshalb haengt seit #97 auch die Telefonsperre des Onboardings an ihm
 * (`users.phone_number_verified_at`, gesetzt in completeValidation()).
 *
 * Gespeichert wird immer E.164. Eine Nummer, die Twilio nicht waehlen kann,
 * wird gar nicht erst angelegt. Festnetz ist ausdruecklich erlaubt -- ein
 * Handwerksbetrieb erreicht seine Leads ueber die Werkstattnummer, und der
 * Anruf-Weg kann das im Gegensatz zur SMS.
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
     * Festnetz ist ausdruecklich erlaubt -- geprueft wird, ob die Nummer
     * waehlbar ist und aus einem erlaubten Land stammt (`normalize()`). Ein
     * Handwerksbetrieb erreicht seine Leads ueber die Werkstattnummer.
     *
     * @throws InvalidArgumentException wenn die Eingabe keine waehlbare Nummer
     *                                  ist, aus einem nicht erlaubten Land stammt oder schon einem anderen
     *                                  Konto gehoert. Die Meldung ist uebersetzt und darf dem Nutzer gezeigt
     *                                  werden.
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

        // Der Rueckruf von Twilio kann schneller sein als diese Antwort. Ist er
        // schon durch, steht die Zeile nicht mehr auf `pending` -- dann wird
        // nur die Kennung des Vorgangs nachgetragen und nicht der angesagte
        // Code, der an einer fertigen Zeile nichts mehr zu suchen hat.
        $callerId->forceFill(array_merge(
            ['validation_sid' => $validation->callSid],
            $callerId->fresh()?->status === CallerIdStatus::PENDING
                ? ['validation_code' => $validation->validationCode]
                : [],
        ))->save();

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
     *
     * **Hier entsteht seit Ticket #97 auch die Telefonbestaetigung des
     * Nutzers** (`users.phone_number_verified_at`). Vorher setzte sie der
     * abgeloeste SMS-Weg, und eine so bestaetigte Nummer stand in keinem
     * Twilio-Konto -- der Anruf beim Lead scheiterte dann mit 21210 (#96).
     * Der Bestaetigungsanruf beweist beides in einem Vorgang: Der Nutzer hat
     * das Telefon in der Hand, und die Nummer liegt bei Twilio als Verified
     * Caller ID. Deshalb genau ein Setzweg, und er steht hier.
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

        $this->markUserVerified($callerId);

        return $callerId;
    }

    /**
     * Schreibt die Telefonbestaetigung am Nutzer fest und loest die Folgen aus.
     *
     * Der einzige Setzweg auf `users.phone_number_verified_at`. Auf null
     * gesetzt wird sie an zwei Stellen -- im Admin (Zuruecksetzen) und beim
     * Anonymisieren eines Kontos --, gesetzt nur hier.
     *
     * Der Kanal ist immer `call`: Einen anderen gibt es seit Ticket #97 nicht
     * mehr. Die Spalte bleibt, weil im Bestand `sms` steht und der Support
     * sehen muss, auf welchem Weg eine alte Bestaetigung entstand.
     *
     * Idempotent: Ein zweiter Rueckruf zu derselben Nummer findet die Zeile
     * nicht mehr auf `pending` und kommt hier nicht an.
     */
    private function markUserVerified(CallerId $callerId): void
    {
        $user = $callerId->user;

        if (! $user instanceof User) {
            return;
        }

        $user->forceFill([
            'phone_e164' => $callerId->phone_number,
            // Die Eingabeform bleibt stehen, falls sie schon da war -- sonst
            // steht am Konto keine Nummer, die ein Mensch wiedererkennt.
            'phone_number' => $user->phone_number ?: $callerId->phone_number,
            'phone_number_verified_at' => now(),
            'phone_verification_channel' => PhoneVerificationChannel::CALL,
        ])->save();

        event(new PhoneVerified($user, PhoneVerificationChannel::CALL));

        // Das aeltere Ereignis aus SaaSykit: An ihm haengt die Freischaltung
        // von Abos, die auf die Verifizierung warten.
        UserPhoneVerified::dispatch($user);
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
     * Eine Nummer ohne Laendervorwahl wird gegen
     * `funnel.call.caller_id.region` gelesen, eine Nummer mit eigener Vorwahl
     * muss aus `funnel.call.caller_id.regions` kommen (Ticket #104). Die Liste
     * ist auch eine Kostenbremse: Jeder Bestaetigungsanruf kostet Geld, und
     * ein Anruf ausserhalb Europas kostet ein Vielfaches eines deutschen. Die
     * harte Grenze sind die Voice Geographic Permissions im Twilio-Konto --
     * diese Pruefung ist die freundliche davor, denn eine von Twilio
     * abgewiesene Anforderung landet nur in `failed` und sagt dem Nutzer
     * nicht, woran es lag.
     *
     * Die Meldungen sind uebersetzt: Die Aufrufer zeigen sie dem Nutzer
     * unveraendert, damit er die Laender-Ablehnung von einer unlesbaren
     * Eingabe unterscheiden kann.
     *
     * @throws InvalidArgumentException
     */
    public function normalize(string $input): string
    {
        $region = (string) config('funnel.call.caller_id.region');

        try {
            $parsed = $this->phoneNumbers->parse(trim($input), $region);
        } catch (NumberParseException) {
            throw new InvalidArgumentException(__('call.caller_id.invalid_number'));
        }

        if (! $this->phoneNumbers->isValidNumber($parsed)) {
            throw new InvalidArgumentException(__('call.caller_id.invalid_number'));
        }

        /** @var list<string> $allowed */
        $allowed = (array) config('funnel.call.caller_id.regions', []);
        $numberRegion = $this->phoneNumbers->getRegionCodeForNumber($parsed);

        if ($allowed !== [] && ! in_array($numberRegion, $allowed, strict: true)) {
            throw new InvalidArgumentException(__('call.caller_id.unsupported_region'));
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
     * Eine Nummer gehoert genau einem Konto.
     *
     * Sonst waere die Rufnummernanzeige des Click-to-Call kein Nachweis mehr:
     * Zwei Kaeufer riefen mit derselben Nummer an, und der Lead koennte den
     * Rueckruf nicht zuordnen. Gefragt werden beide Tabellen -- die eigene
     * Zeile in `caller_ids` und die Bestaetigung am Benutzer. Die zweite
     * Haelfte ist der Bestand: Wer seine Nummer ueber den abgeloesten SMS-Weg
     * bestaetigt hat, hat dazu keine `caller_ids`-Zeile.
     *
     * Die Meldung nennt bewusst nicht, welches Konto es ist -- das waere eine
     * Auskunft ueber einen anderen Kunden.
     *
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

        if (! $taken) {
            $taken = User::query()
                ->where('phone_e164', $phoneNumber)
                ->whereNotNull('phone_number_verified_at')
                ->whereKeyNot($user->getKey())
                ->exists();
        }

        if ($taken) {
            throw new InvalidArgumentException(__('call.caller_id.taken'));
        }
    }

    /**
     * Nimmt die Anforderungssperre eines Nutzers zurueck (LP-ONBOARD-022).
     *
     * Der Fall: Der Nutzer hat sein Fenster leergedrueckt und ruft genau
     * deshalb den Support an. Ohne diesen Weg stuende das Konto bis zum Ablauf
     * des Fensters, auch nachdem der Support alles andere erledigt hat.
     *
     * Zurueckgenommen werden beide Zaehler -- der am Nutzer und der an seiner
     * Nummer, denn die Sperre an der Nummer traegt er sonst weiter mit. Die
     * Wartezeit von 60 Sekunden bleibt stehen: Sie laeuft von selbst ab, und
     * sie ist nicht der Grund, aus dem jemand den Support anruft.
     *
     * @return int Anzahl der zurueckgenommenen Zaehler.
     */
    public function releaseRequestLimit(User $user): int
    {
        $released = 0;

        foreach (array_keys($this->limiterKeys($this->forUser($user)?->phone_number, $user)) as $key) {
            if (RateLimiter::attempts($key) > 0) {
                $released++;
            }

            RateLimiter::clear($key);
        }

        return $released;
    }

    /**
     * Angeforderte Bestaetigungsanrufe dieses Nutzers im laufenden Fenster.
     *
     * Der Admin entscheidet daran, ob der Knopf "Sperre aufheben" ueberhaupt
     * etwas zu tun haette (LP-ONBOARD-022).
     */
    public function countingRequestsInWindow(User $user): int
    {
        return RateLimiter::attempts($this->userLimiterKey($user));
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
    private function limiterKeys(?string $phoneNumber, User $user): array
    {
        $limits = (array) config('funnel.call.caller_id.limits');
        $keys = [];

        if ($phoneNumber !== null && $phoneNumber !== '') {
            $keys['caller-id-validation:phone:'.$phoneNumber] = (int) ($limits['requests_per_phone'] ?? 0);
        }

        $keys[$this->userLimiterKey($user)] = (int) ($limits['requests_per_user'] ?? 0);

        return $keys;
    }

    private function userLimiterKey(User $user): string
    {
        return 'caller-id-validation:user:'.$user->getKey();
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
