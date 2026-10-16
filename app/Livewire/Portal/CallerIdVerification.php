<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Constants\CallerIdStatus;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\CallerId;
use App\Models\User;
use App\Services\CallerIdService;
use App\Services\Twilio\CallerIdValidationFailed;
use App\Services\Twilio\CallerIdValidationThrottled;
use App\Support\PortalCallerIdLock;
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
 * ihn auf der Tastatur eintippen.
 *
 * **Seit Ticket #98 gibt es deshalb keinen Schritt "Code eingeben" mehr.** Er
 * war das letzte Stueck des SMS-Entwurfs: ein Feld, das die Eingabe mit dem
 * angezeigten Code verglich und danach trotzdem auf Twilio warten musste. Drei
 * Schritte sind daraus zwei geworden -- Nummer eingeben, Anruf annehmen.
 */
#[Layout('components.layouts.portal-app')]
class CallerIdVerification extends Component
{
    use InteractsWithPortalTenant;

    public const STATE_ENTER = 'enter';

    public const STATE_CALLING = 'calling';

    public const STATE_DONE = 'done';

    public string $state = self::STATE_ENTER;

    public string $label = '';

    public string $countryCode = '+49';

    public string $phone = '';

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
            // Fehlt eine nutzbare Nummer, steht der Grund oben auf der Seite
            // (App\Support\PortalCallerIdLock). Eine Sperre ist das seit
            // Ticket #49 nicht mehr -- der Weg zurueck bleibt daneben stehen.
            'missingNumber' => PortalCallerIdLock::locks($this->portalUser(), $this->portalTenant()),
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
        } catch (InvalidArgumentException $exception) {
            // Die Meldung kommt uebersetzt aus dem Dienst: unlesbare Eingabe,
            // ein nicht erlaubtes Land (Ticket #104) oder eine Nummer, die
            // schon einem anderen Konto gehoert. Ein fester Text waere hier
            // irrefuehrend -- der Nutzer soll wissen, was er aendern kann.
            $this->notice = $exception->getMessage();
            $this->state = self::STATE_ENTER;

            return;
        } catch (CallerIdValidationThrottled $exception) {
            // Ticket #97: Eine Anforderung zu viel. Es ist nichts geschrieben
            // und nichts gewaehlt worden -- der Nutzer bleibt im ersten
            // Schritt und bekommt die Wartezeit genannt.
            $this->notice = $exception->retryAfterSeconds > 0
                ? __('call.caller_id.throttled', ['seconds' => $exception->retryAfterSeconds])
                : __('call.caller_id.throttled_window');
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

        if ($callerId->status->isFailure()) {
            // Ticket #97: Warum es nicht geklappt hat, steht am Stand --
            // "nicht angenommen" verlangt etwas anderes vom Nutzer als "Code
            // nicht bestaetigt".
            $this->notice = $callerId->status->failureHint() ?? __('call.caller_id.portal.failed');
            $this->state = self::STATE_ENTER;
        }
    }

    public function goToEnter(): void
    {
        $this->notice = null;
        $this->state = self::STATE_ENTER;
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
     * Die Portalnummer, die der Anfragende sieht -- gruppiert fuer Screenreader
     * und Auge. Gezeigt wird sie nur, wenn die Nummer dem Twilio-Konto
     * *gehoert* (`twilio.from_number_is_owned`). "Gesetzt" ist nicht "eigen":
     * solange `TWILIO_FROM` auf eine privat verifizierte Nummer zeigt, waere
     * jede Anzeige ein falsches Versprechen. Der Schalter kostet bewusst keine
     * Twilio-Abfrage pro Seitenaufruf.
     */
    private function portalNumber(): ?string
    {
        if (! config('twilio.from_number_is_owned')) {
            return null;
        }

        $number = (string) config('twilio.from_number');

        if ($number === '') {
            $number = (string) config('services.twilio.from');
        }

        return $number === '' ? null : $this->groupNumber($number);
    }

    /** Aus "+4915115509444" wird "+49 151 1550 9444". */
    private function groupNumber(string $number): string
    {
        $digits = $this->digits($number);

        if ($digits === '') {
            return $number;
        }

        $countryDigits = $this->digits($this->countryCode);
        $countryDigits = ($countryDigits !== '' && str_starts_with($digits, $countryDigits))
            ? $countryDigits
            : substr($digits, 0, 2);

        $rest = substr($digits, strlen($countryDigits));

        if ($rest === '') {
            return '+'.$countryDigits;
        }

        $groups = [substr($rest, 0, 3)];
        $tail = substr($rest, 3);

        foreach (str_split($tail, 4) as $chunk) {
            if ($chunk === '') {
                continue;
            }

            if (strlen($chunk) === 1) {
                $groups[count($groups) - 1] .= $chunk;

                continue;
            }

            $groups[] = $chunk;
        }

        return '+'.$countryDigits.' '.implode(' ', $groups);
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
