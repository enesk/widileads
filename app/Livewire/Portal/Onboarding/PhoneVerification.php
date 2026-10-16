<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Onboarding;

use App\Constants\CallerIdStatus;
use App\Constants\TenantType;
use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallerIdService;
use App\Services\Twilio\CallerIdValidationFailed;
use App\Services\Twilio\CallerIdValidationThrottled;
use App\Support\PhoneMask;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Die Rufnummernbestaetigung des Kaeufers (LP-ONBOARD-005, Ticket #97).
 *
 * Der erste Bildschirm, den ein neues Konto sieht. Drei Zustaende in EINER
 * Komponente -- Nummer, Anruf, Erfolg --, weil es derselbe Vorgang ist: Die
 * Nummer vom ersten Schritt ist die Nummer, die klingelt, und der Wechsel darf
 * nicht neu laden. Drei Routen daraus zu machen hiesse, den halbfertigen
 * Vorgang zwischen Seitenaufrufen zu tragen.
 *
 * **Seit Ticket #97 ist der Weg der Bestaetigungsanruf und nicht mehr die
 * SMS.** Twilio ruft die Nummer an und laesst den Code am Telefon eintippen;
 * die Nummer landet dadurch als Verified Caller ID im Twilio-Konto. Der
 * abgeloeste SMS-Weg bewies denselben Besitz, legte dort aber nichts an -- der
 * Anruf beim Lead scheiterte danach mit Fehler 21210 (#96). Der Code steht
 * deshalb jetzt auf dieser Seite und wird nicht hier eingetippt.
 *
 * **Hier wird nichts entschieden.** Ob eine Nummer waehlbar ist, ob ein Limit
 * erreicht ist, und vor allem: ob die Nummer bestaetigt ist -- alles im
 * App\Services\CallerIdService. Diese Klasse uebersetzt dessen Ausnahmen in
 * Feldfehler und faehrt den Zustandswechsel. Ein Weg, der von hier aus
 * `verified` oder `users.phone_number_verified_at` setzt, darf nicht entstehen:
 * Beides setzt ausschliesslich der Rueckruf von Twilio im Dienst.
 *
 * **Der Zaehler bis zum naechsten Anruf** ist eine Zahl in Sekunden, die der
 * Server mitgibt; herunterzaehlen tut Alpine im Markup. Abgelaufen ist er
 * trotzdem erst, wenn der Dienst das sagt: Der Knopf fuehrt bei zu fruehem
 * Druck in dieselbe Ausnahme wie jeder andere Weg.
 *
 * Die Oberflaeche bleibt die aus dem SMS-Entwurf mit dem noetigen Minimum an
 * Umbau; der Entwurf fuer den Anruf-Weg laeuft als #98.
 */
class PhoneVerification extends Component
{
    /**
     * Die drei Zustaende. `calling` hiess im SMS-Weg `code` -- gewartet wird
     * jetzt auf Twilios Rueckmeldung und nicht auf eine Eingabe.
     */
    public const STATE_NUMBER = 'number';

    public const STATE_CALLING = 'calling';

    public const STATE_SUCCESS = 'success';

    #[Locked]
    public string $state = self::STATE_NUMBER;

    /**
     * Die Laendervorwahl als Text. Keine Flaggen-Emoji: Sie sind auf Windows
     * nicht vorhanden, und eine Flagge ist ohnehin ein Staat und kein
     * Nummernraum.
     */
    public string $countryCode = '+49';

    /**
     * Die Nummer, wie der Nutzer sie tippt -- ohne Laendervorwahl.
     */
    public string $phone = '';

    /**
     * Die angerufene Nummer in E.164, sobald ein Anruf laeuft.
     */
    #[Locked]
    public ?string $phoneE164 = null;

    /**
     * Der Code, den die Ansage verlangt. Er steht auf dem Bildschirm und wird
     * am Telefon eingetippt -- nicht umgekehrt.
     */
    #[Locked]
    public ?string $expectedCode = null;

    /**
     * Sekunden, bis wieder angerufen werden darf. Null heisst sofort.
     *
     * Deckt beide Faelle ab: die Sperrfrist nach einer Anforderung und die
     * Wartezeit aus einem erreichten Limit. Fuer die Oberflaeche ist es
     * dasselbe -- der Knopf geht gerade nicht, und hier steht wie lange.
     */
    #[Locked]
    public int $resendIn = 0;

    /**
     * Wie oft $resendIn in dieser Sitzung neu gesetzt wurde.
     *
     * Dient allein dem wire:key des Zaehlerblocks. Der Zaehler laeuft in
     * Alpine, und Alpine setzt ein x-data nur dann neu auf, wenn Livewire das
     * Element austauscht statt es zu morphen. Am Wert von $resendIn laesst
     * sich das nicht festmachen: Nach einer erneuten Anforderung steht dort
     * wieder dieselbe Sperrfrist (60), der Schluessel bliebe gleich, und der
     * Zaehler im Browser bliebe auf null stehen. Diese Zahl steigt bei jedem
     * Setzen und macht den Schluessel dadurch verlaesslich neu.
     */
    #[Locked]
    public int $resendTick = 0;

    public function mount(CallerIdService $callerIds): void
    {
        $callerId = $this->currentCallerId($callerIds);

        if (! $callerId instanceof CallerId) {
            return;
        }

        /*
         | Eine laufende Bestaetigung wird aufgenommen, statt den Ablauf von
         | vorn zu beginnen: Wer die Seite neu laedt oder sie auf dem Telefon
         | aus dem Hintergrund holt, landet dann wieder beim Code statt bei der
         | Nummer -- sonst waere der erste Weg nach dem Neuladen ein zweiter
         | Anruf, und der ist gesperrt.
         */
        if ($callerId->status === CallerIdStatus::PENDING) {
            $this->enterCall($callerId);

            return;
        }

        // Ein Fehlversuch aus einer frueheren Sitzung: Die Nummer steht wieder
        // im Feld, und darueber steht, warum es nicht geklappt hat.
        $this->phone = $this->localPart($callerId->phone_number);

        if ($callerId->status->isFailure()) {
            $this->addError('phone', (string) $callerId->status->failureHint());
        }
    }

    /**
     * "Anruf anfordern" -- Screen 1.
     */
    public function send(CallerIdService $callerIds): void
    {
        $this->resetErrorBag();

        if (trim($this->phone) === '') {
            $this->addError('phone', __('portal.onboarding.phone.errors.required'));

            return;
        }

        $this->requestCall($callerIds, trim($this->countryCode.' '.$this->phone));
    }

    /**
     * "Erneut anrufen lassen" -- Screen 2. Geht an die festgelegte Nummer.
     */
    public function resend(CallerIdService $callerIds): void
    {
        $this->resetErrorBag();

        if ($this->phoneE164 === null) {
            $this->editNumber();

            return;
        }

        $this->requestCall($callerIds, $this->phoneE164);
    }

    /**
     * Zieht den Stand nach, waehrend der Anruf laeuft (wire:poll im Markup).
     *
     * Gemeldet hat ihn Twilio an den Rueckruf des Dienstes, diese Seite liest
     * ihn nur. Auch die Telefonsperre ist damit schon gefallen -- der Dienst
     * setzt `users.phone_number_verified_at` im selben Zug.
     */
    public function refreshStatus(CallerIdService $callerIds): void
    {
        $callerId = $this->currentCallerId($callerIds);

        if (! $callerId instanceof CallerId) {
            $this->backToNumber();

            return;
        }

        if ($callerId->status === CallerIdStatus::VERIFIED) {
            $this->state = self::STATE_SUCCESS;
            $this->expectedCode = null;

            return;
        }

        if ($callerId->status->isFailure()) {
            // Ticket #97: Warum es nicht geklappt hat, steht am Stand --
            // "nicht angenommen" verlangt etwas anderes vom Nutzer als "Code
            // nicht bestaetigt", und beides ist besser als ein "wartet", auf
            // das nichts folgt.
            $this->backToNumber();
            $this->addError('phone', (string) $callerId->status->failureHint());
        }
    }

    /**
     * "Nummer aendern" -- zurueck zu Screen 1.
     */
    public function editNumber(): void
    {
        $this->resetErrorBag();
        $this->backToNumber();
        $this->setResendIn(0);
    }

    /**
     * "Los geht's" -- Screen 3.
     *
     * Ziel ist die gemerkte Adresse (`redirect()->intended`), die
     * EnsurePhoneVerified beim Wegschicken hinterlegt hat. Ob von dort aus der
     * Wizard kommt, entscheidet EnsureOnboardingWizard und nicht diese
     * Komponente: Ein Bestandskunde mit erledigtem Wizard landet dann dort, wo
     * er hinwollte, ein neuer Kaeufer im ersten Schritt. Dieselbe Entscheidung
     * hier ein zweites Mal zu treffen hiesse, sie an zwei Stellen zu pflegen.
     */
    public function finish(): void
    {
        $this->redirect(
            session()->pull('url.intended') ?? route('portal.home'),
            navigate: false,
        );
    }

    public function render(): View
    {
        return view('livewire.portal.onboarding.phone-verification', [
            'maskedPhone' => PhoneMask::partial($this->phoneE164),
        ]);
    }

    /**
     * Der gemeinsame Weg beider Anruf-Knoepfe.
     */
    private function requestCall(CallerIdService $callerIds, string $input): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $workspace = $this->workspaceFor($user);

        if (! $workspace instanceof Tenant) {
            // Ohne Workspace gibt es keine Zeile, an der die Nummer haengen
            // koennte. Das ist ein Datenfehler und keine Eingabe des Nutzers --
            // er soll deshalb nicht raten, was er falsch gemacht hat.
            $this->addError('phone', __('portal.onboarding.phone.errors.no_workspace'));

            return;
        }

        try {
            $callerId = $callerIds->requestValidation(
                $workspace,
                $user,
                $input,
                route('twilio.caller-id.validation-status'),
            );
        } catch (InvalidArgumentException $exception) {
            // Unlesbare Nummer oder eine, die schon einem anderen Konto
            // gehoert. Beides haengt an der Nummer, auch wenn der Nutzer
            // gerade auf dem Anruf-Screen steht.
            $this->backToNumber();
            $this->addError('phone', $exception->getMessage());

            return;
        } catch (CallerIdValidationThrottled $exception) {
            // Eine Anforderung zu viel. Es ist nichts geschrieben und nichts
            // gewaehlt worden -- der Nutzer bekommt die Wartezeit genannt.
            $this->setResendIn($exception->retryAfterSeconds);
            $this->addError(
                $this->state === self::STATE_CALLING ? 'code' : 'phone',
                $exception->retryAfterSeconds > 0
                    ? __('call.caller_id.throttled', ['seconds' => $exception->retryAfterSeconds])
                    : __('call.caller_id.throttled_window'),
            );

            return;
        } catch (CallerIdValidationFailed $exception) {
            // Die Twilio-Meldung nennt Kontodetails und gehoert ins Log.
            logger()->error('Twilio lehnte den Bestaetigungsanruf ab.', [
                'message' => $exception->getMessage(),
            ]);

            $this->backToNumber();
            $this->addError('phone', __('call.caller_id.provider_failed'));

            return;
        }

        $this->enterCall($callerId);
    }

    /**
     * Auf den Anruf-Screen wechseln -- aus einer frischen Anforderung oder aus
     * einer, die beim Laden der Seite noch lief.
     */
    private function enterCall(CallerId $callerId): void
    {
        $this->state = self::STATE_CALLING;
        $this->phoneE164 = $callerId->phone_number;
        $this->phone = $this->localPart($callerId->phone_number);
        $this->expectedCode = $callerId->validation_code;
        $this->setResendIn($this->secondsUntilResend($callerId));
    }

    /**
     * Zurueck auf Screen 1 -- von Hand ueber "Nummer aendern" oder weil der
     * Anruf nicht geklappt hat.
     *
     * Die Meldung daran bringt den Fokus ins Nummernfeld. Ohne sie stuende er
     * nach dem Wechsel irgendwo im alten Markup, und wer mit der Tastatur
     * arbeitet, muesste sich erst wieder hinuebertasten.
     */
    private function backToNumber(): void
    {
        $this->state = self::STATE_NUMBER;
        $this->expectedCode = null;

        $this->dispatch('number-screen-entered');
    }

    /**
     * Die Nummer dieses Nutzers samt Stand, Verfallenes bereits
     * gekennzeichnet.
     */
    private function currentCallerId(CallerIdService $callerIds): ?CallerId
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $callerId = $callerIds->forUser($user);

        return $callerId instanceof CallerId ? $callerIds->expireIfOverdue($callerId) : null;
    }

    /**
     * Der Workspace, in dem die Nummer angelegt wird.
     *
     * Der Kaeufer-Workspace, denn nur dort wird telefoniert. Steht der Nutzer
     * in keinem (ein Verkaeufer, den die Telefonsperre genauso trifft), wird
     * der aelteste genommen: Die Bestaetigung haengt am Benutzer, die Zeile
     * braucht aber einen Mandanten.
     */
    private function workspaceFor(User $user): ?Tenant
    {
        return $user->tenants()
            ->where('type', TenantType::BUYER)
            ->orderBy('tenants.id')
            ->first()
            ?? $user->tenants()->orderBy('tenants.id')->first();
    }

    /**
     * Wie lange noch, bis erneut angefordert werden darf?
     *
     * Gerechnet aus `requested_at` der Zeile und derselben Konfiguration, die
     * der Dienst durchsetzt -- eine zweite Quelle liefe auseinander.
     */
    private function secondsUntilResend(CallerId $callerId): int
    {
        $wait = (int) config('funnel.call.caller_id.limits.resend_after_seconds');

        if ($wait <= 0 || $callerId->requested_at === null) {
            return 0;
        }

        $elapsed = (int) $callerId->requested_at->diffInSeconds(now());

        return max(0, $wait - $elapsed);
    }

    /**
     * Die Wartezeit setzen -- und den Schluessel des Zaehlerblocks mitziehen.
     */
    private function setResendIn(int $seconds): void
    {
        $this->resendIn = max(0, $seconds);
        $this->resendTick++;
    }

    /** Aus "+491721234567" wird "1721234567" fuer das Eingabefeld. */
    private function localPart(?string $phoneNumber): string
    {
        if ($phoneNumber === null) {
            return '';
        }

        return str_starts_with($phoneNumber, $this->countryCode)
            ? trim(substr($phoneNumber, strlen($this->countryCode)))
            : $phoneNumber;
    }
}
