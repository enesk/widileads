<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\CallerIdStatus;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\CallerId;
use App\Models\User;
use App\Services\CallerIdService;
use App\Services\Twilio\CallerIdValidationFailed;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Die eigene Rufnummer bestaetigen -- im Portal (Portal Phase 1).
 *
 * Zweitfassung der Filament-Seite App\Filament\Dashboard\Pages\CallerId, hier
 * als vierschrittiger Ablauf nach dem Entwurf `rufnummer-bestaetigen.html`.
 * Beide Wege laufen parallel, bis das Portal abgenommen ist.
 *
 * **Diese Komponente bestaetigt nichts.** Den Stand einer Nummer setzt
 * ausschliesslich der CallerIdService: einmal beim Anfordern (`pending`) und
 * einmal, wenn Twilio das Ergebnis meldet (`verified` oder `failed`, ueber
 * CallerIdValidationController). Was hier passiert, ist Anfordern und Zusehen.
 * Ein Weg, der von dieser Seite aus `verified` setzt, darf nicht entstehen --
 * sonst ist die Bestaetigung wertlos.
 *
 * **Abweichung zum Entwurf:** Der Entwurf beschreibt Twilio Verify, wo eine
 * Ansage den Code vorliest und man ihn auf der Seite eintippt. Gebaut ist die
 * Outgoing-Caller-ID-Pruefung: Wir zeigen den Code, Twilio ruft an und laesst
 * ihn auf der Tastatur eintippen. Das Markup bleibt wie geliefert; der Schritt
 * "Code eingeben" gleicht die Eingabe deshalb mit dem angesagten Code ab und
 * wartet danach auf die Rueckmeldung von Twilio, die weiterhin allein
 * entscheidet.
 */
#[Layout('components.layouts.portal-app')]
class CallerIdVerification extends Component
{
    use InteractsWithPortalTenant;

    public const STATE_ENTER = 'enter';

    public const STATE_CALLING = 'calling';

    public const STATE_CODE = 'code';

    public const STATE_DONE = 'done';

    /** Erlaubte Versuche, den angesagten Code einzutippen. */
    private const CODE_ATTEMPTS = 3;

    public string $state = self::STATE_ENTER;

    public string $label = '';

    public string $countryCode = '+49';

    public string $phone = '';

    /** Die sechs Ziffern, vom Skript als ein Wert zusammengesetzt. */
    public string $code = '';

    public ?string $codeError = null;

    public int $attemptsLeft = self::CODE_ATTEMPTS;

    public bool $isDefault = true;

    /** Meldung ueber dem Ablauf, etwa wenn Twilio die Anforderung ablehnt. */
    public ?string $notice = null;

    public function mount(): void
    {
        $callerId = $this->callerId();

        if (! $callerId instanceof CallerId) {
            return;
        }

        // Eine laufende oder fertige Bestaetigung wird aufgenommen, statt den
        // Ablauf von vorn zu beginnen: Der Anruf kann aus einem anderen Fenster
        // stammen.
        $this->state = match ($callerId->status) {
            CallerIdStatus::PENDING => self::STATE_CALLING,
            CallerIdStatus::VERIFIED => self::STATE_DONE,
            default => self::STATE_ENTER,
        };

        if ($this->state !== self::STATE_ENTER) {
            $this->phone = $this->localPart($callerId->phone_number);
            $this->label = (string) ($callerId->label ?? '');
        }
    }

    public function render(): View
    {
        $callerId = $this->callerId();

        return view('livewire.portal.caller-id-verification', [
            'callerId' => $callerId,
            'displayNumber' => $this->displayNumber($callerId),
            'expectedCode' => $callerId?->status === CallerIdStatus::PENDING ? $callerId->validation_code : null,
            'verified' => $this->verifiedNumbers(),
            'portalNumber' => $this->portalNumber(),
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $this->portalTenant()->uuid]),
            // Eine eigene Liste der Rufnummern gibt es noch nicht. Der Weg
            // zurueck fuehrt deshalb in die Uebersicht, der Knopf nach der
            // Bestaetigung auf diese Seite -- dort steht die Nummer dann unter
            // "Bereits bestaetigt".
            'backUrl' => route('portal.overview', ['tenant' => $this->portalTenant()->uuid]),
            'numbersUrl' => route('portal.caller-id', ['tenant' => $this->portalTenant()->uuid]),
        ]);
    }

    /**
     * Fordert den Bestaetigungsanruf an.
     *
     * Der Name darf NICHT `call` lauten: `$wire.call(methode)` ist die
     * eingebaute Livewire-Schnittstelle im Browser.
     */
    public function startCall(CallerIdService $callerIds): void
    {
        $this->notice = null;
        $this->codeError = null;
        $this->code = '';
        $this->attemptsLeft = self::CODE_ATTEMPTS;

        $user = $this->portalUser();

        if (! $user instanceof User) {
            return;
        }

        try {
            $callerIds->requestValidation(
                $this->portalTenant(),
                $user,
                $this->countryCode.' '.$this->phone,
                route('twilio.caller-id.validation-status'),
            );
        } catch (InvalidArgumentException) {
            $this->notice = __('call.caller_id.invalid_number');
            $this->state = self::STATE_ENTER;

            return;
        } catch (CallerIdValidationFailed $exception) {
            // Die Twilio-Meldung nennt Kontodetails und gehoert ins Log.
            logger()->error('Twilio lehnte die Rufnummern-Bestaetigung ab.', [
                'message' => $exception->getMessage(),
            ]);

            $this->notice = __('call.caller_id.provider_failed');
            $this->state = self::STATE_ENTER;

            return;
        }

        $this->state = self::STATE_CALLING;
    }

    /**
     * Zieht den Stand nach, waehrend der Anruf laeuft. Gemeldet hat ihn Twilio
     * an den Rueckruf, diese Seite liest ihn nur.
     */
    public function refreshStatus(): void
    {
        $callerId = $this->callerId();

        if (! $callerId instanceof CallerId) {
            return;
        }

        if ($callerId->status === CallerIdStatus::VERIFIED) {
            $this->state = self::STATE_DONE;

            return;
        }

        if (in_array($callerId->status, [CallerIdStatus::FAILED, CallerIdStatus::EXPIRED], true)) {
            $this->notice = __('call.caller_id.portal.failed');
            $this->state = self::STATE_ENTER;
        }
    }

    public function goToCode(): void
    {
        $this->codeError = null;
        $this->state = self::STATE_CODE;
    }

    public function goToEnter(): void
    {
        $this->notice = null;
        $this->state = self::STATE_ENTER;
    }

    /**
     * Gleicht die Eingabe mit dem angesagten Code ab.
     *
     * Das ist ausdruecklich KEINE Bestaetigung: Bestaetigt ist eine Nummer erst,
     * wenn Twilio es meldet. Stimmt der Code und steht die Rueckmeldung noch
     * aus, bleibt die Seite im Schritt und fragt weiter nach.
     */
    public function verifyCode(): void
    {
        $callerId = $this->callerId();

        if (! $callerId instanceof CallerId) {
            $this->state = self::STATE_ENTER;

            return;
        }

        if ($callerId->status === CallerIdStatus::VERIFIED) {
            $this->state = self::STATE_DONE;

            return;
        }

        $expected = (string) ($callerId->validation_code ?? '');

        if ($expected !== '' && $this->digits($this->code) !== $expected) {
            $this->attemptsLeft = max(0, $this->attemptsLeft - 1);

            $this->codeError = $this->attemptsLeft > 0
                ? __('call.caller_id.portal.code_wrong', ['count' => $this->attemptsLeft])
                : __('call.caller_id.portal.code_exhausted');

            if ($this->attemptsLeft === 0) {
                $this->state = self::STATE_ENTER;
                $this->attemptsLeft = self::CODE_ATTEMPTS;
            }

            return;
        }

        // Code stimmt, Twilio hat aber noch nicht gemeldet: zurueck in den
        // Anruf, dort wird nachgefragt.
        $this->codeError = null;
        $this->state = self::STATE_CALLING;
    }

    /** Der Stand der eigenen Nummer, Verfallenes bereits gekennzeichnet. */
    private function callerId(): ?CallerId
    {
        $user = $this->portalUser();

        if (! $user instanceof User) {
            return null;
        }

        $service = app(CallerIdService::class);
        $callerId = $service->forUser($user);

        return $callerId instanceof CallerId ? $service->expireIfOverdue($callerId) : null;
    }

    /**
     * Die bereits bestaetigten Nummern. Je Benutzer gibt es genau eine, die
     * Liste bleibt trotzdem eine Liste -- der Entwurf zeigt sie so, und mehr
     * als eine Nummer je Mitarbeiter ist die naheliegende Erweiterung.
     *
     * @return list<array{label: string, number: string}>
     */
    private function verifiedNumbers(): array
    {
        $callerId = $this->callerId();

        if (! $callerId instanceof CallerId || $callerId->status !== CallerIdStatus::VERIFIED) {
            return [];
        }

        return [[
            'label' => (string) ($callerId->label ?? __('call.caller_id.portal.default_label')),
            'number' => (string) $callerId->phone_number,
        ]];
    }

    private function displayNumber(?CallerId $callerId): string
    {
        if ($callerId instanceof CallerId && $callerId->phone_number !== null) {
            return (string) $callerId->phone_number;
        }

        return trim($this->countryCode.' '.$this->phone);
    }

    /**
     * Die Portalnummer, die der Anfragende sieht. Kanonisch aus der
     * Twilio-Einstellung; ohne hinterlegte Nummer wird die Kachel nicht
     * gezeigt, statt eine erfundene zu drucken.
     */
    private function portalNumber(): ?string
    {
        $number = (string) config('twilio.from_number');

        if ($number === '') {
            $number = (string) config('services.twilio.from');
        }

        return $number === '' ? null : $number;
    }

    /** Aus "+49172554011 8" wird "172 5540 118" fuer das Eingabefeld. */
    private function localPart(?string $phoneNumber): string
    {
        if ($phoneNumber === null) {
            return '';
        }

        return str_starts_with($phoneNumber, $this->countryCode)
            ? trim(substr($phoneNumber, strlen($this->countryCode)))
            : $phoneNumber;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
