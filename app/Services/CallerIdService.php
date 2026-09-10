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
use Illuminate\Support\Facades\DB;
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
     * @throws InvalidArgumentException wenn die Eingabe keine waehlbare Nummer ist.
     * @throws CallerIdValidationFailed wenn Twilio die Anforderung ablehnt.
     */
    public function requestValidation(Tenant $tenant, User $user, string $input, string $statusCallbackUrl): CallerId
    {
        $phoneNumber = $this->normalize($input);

        $this->guardAgainstForeignClaim($phoneNumber, $user);

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
     * Ergebnis des Bestaetigungsanrufs, wie Twilio es zurueckmeldet.
     *
     * Zugeordnet wird ueber die Nummer: Der Rueckruf traegt keine unserer
     * Kennungen. Eine Nummer, zu der keine offene Bestaetigung liegt, wird
     * stillschweigend verworfen -- ein spaeter Rueckruf darf keinen
     * abgeschlossenen Stand mehr aendern.
     */
    public function completeValidation(string $phoneNumber, bool $verified): ?CallerId
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
                'status' => CallerIdStatus::FAILED,
                'validation_code' => null,
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
     * Name, unter dem die Nummer im Twilio-Konto erscheint. Hilft dort, einen
     * Eintrag einem Menschen zuzuordnen.
     */
    private function friendlyNameFor(User $user): string
    {
        return trim($user->name.' ('.$user->email.')');
    }
}
