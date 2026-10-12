<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Onboarding;

use App\Constants\PhoneVerificationChannel;
use App\Exceptions\PhoneNumberNotRecognizedException;
use App\Exceptions\PhoneNumberTakenException;
use App\Exceptions\PhoneVerificationThrottledException;
use App\Models\PhoneVerificationAttempt;
use App\Models\User;
use App\Services\Phone\PhoneVerificationService;
use App\Services\Twilio\VerifyRequestFailed;
use App\Support\PhoneMask;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Die Rufnummernbestaetigung des Kaeufers (LP-ONBOARD-005).
 *
 * Der erste Bildschirm, den ein neues Konto sieht. Drei Zustaende in EINER
 * Komponente -- Nummer, Code, Erfolg --, weil es derselbe Vorgang ist: Die
 * Nummer vom ersten Schritt ist die Nummer, an die der Code ging, und der
 * Wechsel darf nicht neu laden. Drei Routen daraus zu machen hiesse, den
 * halbfertigen Vorgang zwischen Seitenaufrufen zu tragen.
 *
 * **Hier wird nichts entschieden.** Ob eine Nummer waehlbar ist, ob ein Code
 * stimmt, ob ein Limit erreicht ist: alles im
 * App\Services\Phone\PhoneVerificationService (LP-ONBOARD-004). Diese Klasse
 * uebersetzt dessen Ausnahmen in Feldfehler und faehrt den Zustandswechsel.
 * Insbesondere haelt sie keinen Code und vergleicht keinen -- die Codes liegen
 * bei Twilio Verify.
 *
 * **Die Nummer liegt nach dem Versand in E.164 fest** ($phoneE164, #[Locked]).
 * Der erneute Versand und die Pruefung lesen sie dort und nicht aus den beiden
 * Eingabefeldern: Wer waehrend des Countdowns im Feld weitertippt, soll damit
 * nicht die Nummer wechseln, an die der naechste Code geht.
 *
 * **Der Zaehler bis zum erneuten Senden** ist eine Zahl in Sekunden, die der
 * Server mitgibt; herunterzaehlen tut Alpine im Markup. Ein wire:poll waere
 * eine Anfrage je Sekunde, nur um eine Ziffer kleiner zu machen. Abgelaufen
 * ist er trotzdem erst, wenn der Dienst das sagt: Der Knopf fuehrt bei zu
 * fruehem Druck in dieselbe Ausnahme wie jeder andere Weg.
 */
class PhoneVerification extends Component
{
    /**
     * Die drei Zustaende, wie sie im Ticket heissen.
     */
    public const STATE_NUMBER = 'number';

    public const STATE_CODE = 'code';

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
     * Der zusammengesetzte Code aus den sechs Kaestchen. Gefuellt vom Skript
     * resources/js/modules/code-input.js, nicht von sechs eigenen Feldern.
     */
    public string $code = '';

    /**
     * Die bestaetigte Zielnummer in E.164, sobald ein Code unterwegs ist.
     */
    #[Locked]
    public ?string $phoneE164 = null;

    /**
     * Sekunden, bis wieder gesendet werden darf. Null heisst sofort.
     *
     * Deckt beide Faelle ab: die Sperrfrist nach einem Versand und die
     * Wartezeit aus einem 429. Fuer die Oberflaeche ist es dasselbe -- der
     * Knopf geht gerade nicht, und hier steht wie lange.
     */
    #[Locked]
    public int $resendIn = 0;

    /**
     * Wie oft $resendIn in dieser Sitzung neu gesetzt wurde.
     *
     * Dient allein dem wire:key des Zaehlerblocks. Der Zaehler laeuft in
     * Alpine, und Alpine setzt ein x-data nur dann neu auf, wenn Livewire das
     * Element austauscht statt es zu morphen. Am Wert von $resendIn laesst
     * sich das nicht festmachen: Nach einem erneuten Versand steht dort
     * wieder dieselbe Sperrfrist (60), der Schluessel bliebe gleich, und der
     * Zaehler im Browser bliebe auf null stehen. Diese Zahl steigt bei jedem
     * Setzen und macht den Schluessel dadurch verlaesslich neu.
     */
    #[Locked]
    public int $resendTick = 0;

    /**
     * Wie oft schon erneut gesendet wurde. Ab dem ersten Mal steht der Ausweg
     * "Stattdessen anrufen lassen" daneben -- der Anruf ist teurer und bleibt
     * der Ausweg, nicht der bequemere Weg. Der Dienst setzt dieselbe Grenze
     * noch einmal durch.
     */
    #[Locked]
    public int $resendCount = 0;

    /**
     * Der Kanal des letzten Versands: Davon haengt ab, ob die Zeile "Gesendet
     * an ..." von einer SMS oder von einem Anruf spricht.
     */
    #[Locked]
    public string $channel = PhoneVerificationChannel::SMS->value;

    public function mount(PhoneVerificationService $verification): void
    {
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        /*
         | Ein offener Versuch bedeutet: Es ist schon ein Code unterwegs. Wer
         | die Seite neu laedt oder sie auf dem Telefon aus dem Hintergrund
         | holt, landet dann direkt auf dem Code-Screen statt wieder bei der
         | Nummer -- sonst waere der erste Weg nach dem Neuladen ein zweiter
         | Versand, und der ist gesperrt.
         */
        $attempt = $verification->pendingAttempt($user);

        if (! $attempt instanceof PhoneVerificationAttempt) {
            return;
        }

        $this->state = self::STATE_CODE;
        $this->phoneE164 = $attempt->phone_e164;
        $this->channel = $attempt->channel->value;
        $this->setResendIn($this->secondsUntilResend($verification));
        $this->callAvailableFromHistory($verification);
    }

    /**
     * "Code senden" -- Screen 1.
     */
    public function send(PhoneVerificationService $verification): void
    {
        $this->resetErrorBag();

        $input = trim($this->countryCode.' '.$this->phone);

        if (trim($this->phone) === '') {
            $this->addError('phone', __('portal.onboarding.phone.errors.required'));

            return;
        }

        $this->startVerification($verification, $input, PhoneVerificationChannel::SMS, 'phone');
    }

    /**
     * "Code erneut senden" -- Screen 2. Geht an die festgelegte Nummer.
     */
    public function resend(PhoneVerificationService $verification): void
    {
        $this->resetErrorBag();

        if ($this->phoneE164 === null) {
            $this->editNumber();

            return;
        }

        if ($this->startVerification($verification, $this->phoneE164, PhoneVerificationChannel::SMS, 'code')) {
            $this->resendCount++;
        }
    }

    /**
     * "Stattdessen anrufen lassen" -- der Ausweg, wenn keine SMS ankommt.
     */
    public function sendByCall(PhoneVerificationService $verification): void
    {
        $this->resetErrorBag();

        if ($this->phoneE164 === null) {
            $this->editNumber();

            return;
        }

        if ($this->startVerification($verification, $this->phoneE164, PhoneVerificationChannel::CALL, 'code')) {
            $this->resendCount++;
        }
    }

    /**
     * "Bestaetigen" -- und derselbe Weg, den das Skript bei der sechsten
     * Ziffer von selbst nimmt (wire:submit am Formular).
     */
    public function verify(PhoneVerificationService $verification): void
    {
        $this->resetErrorBag();

        $user = auth()->user();

        if ($user === null || mb_strlen(trim($this->code)) !== 6) {
            $this->failCode(__('portal.onboarding.phone.errors.code_incomplete'));

            return;
        }

        try {
            $verified = $verification->check($user, $this->code);
        } catch (PhoneVerificationThrottledException $exception) {
            // Das Pruefbudget der Sitzung ist aufgebraucht. Die Felder bleiben
            // stehen: Erneut tippen hilft gerade nicht, und ein geleertes Feld
            // saehe aus wie "noch ein Versuch".
            $this->setResendIn($exception->retryAfterSeconds);
            $this->addError('code', $exception->getMessage());

            return;
        } catch (PhoneNumberTakenException $exception) {
            // Zwischen Anforderung und Eingabe hat ein anderes Konto dieselbe
            // Nummer bestaetigt. Zurueck zur Nummer -- diese ist verbraucht.
            $this->backToNumber();
            $this->addError('phone', $exception->getMessage());

            return;
        }

        if (! $verified) {
            $this->failCode($this->codeFailureMessage($verification, $user));

            return;
        }

        $this->state = self::STATE_SUCCESS;
        $this->code = '';
        $this->dispatch('code-screen-entered');
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
            'callOffered' => $this->resendCount > 0,
            'sentByCall' => $this->channel === PhoneVerificationChannel::CALL->value,
        ]);
    }

    /**
     * Zurueck auf Screen 1 -- von Hand ueber "Nummer aendern" oder weil die
     * Nummer selbst das Problem war.
     *
     * Die Meldung daran bringt den Fokus ins Nummernfeld. Ohne sie stuende er
     * nach dem Wechsel irgendwo im alten Markup, und wer mit der Tastatur
     * arbeitet, muesste sich erst wieder hinuebertasten.
     */
    private function backToNumber(): void
    {
        $this->state = self::STATE_NUMBER;
        $this->code = '';

        $this->dispatch('number-screen-entered');
    }

    /**
     * Der gemeinsame Weg aller drei Versandknoepfe.
     *
     * @param  string  $field  Feld, an dem die Fehlermeldung haengt
     * @return bool ob ein Code rausgegangen ist
     */
    private function startVerification(
        PhoneVerificationService $verification,
        string $input,
        PhoneVerificationChannel $channel,
        string $field,
    ): bool {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        try {
            $attempt = $verification->start($user, $input, $channel->value);
        } catch (PhoneNumberNotRecognizedException|PhoneNumberTakenException $exception) {
            // Beides haengt an der Nummer, nicht am Code -- auch wenn der
            // Nutzer gerade auf dem Code-Screen steht.
            $this->backToNumber();
            $this->addError('phone', $exception->getMessage());

            return false;
        } catch (PhoneVerificationThrottledException $exception) {
            $this->setResendIn($exception->retryAfterSeconds);
            $this->addError($field, $exception->getMessage());

            return false;
        } catch (VerifyRequestFailed) {
            // Twilio hat abgelehnt. Der Grund gehoert ins Log und nicht auf
            // den Bildschirm: Der Nutzer kann nur warten.
            $this->addError($field, __('portal.phone_verification.errors.unavailable'));

            return false;
        }

        $this->state = self::STATE_CODE;
        $this->phoneE164 = $attempt->phone_e164;
        $this->channel = $attempt->channel->value;
        $this->code = '';
        $this->setResendIn($this->secondsUntilResend($verification));

        // Der Fokus springt auf das erste Kaestchen, und ein zuvor gesetzter
        // Fehler verschwindet aus den Feldern.
        $this->dispatch('code-screen-entered');

        return true;
    }

    /**
     * Ein falscher oder abgelaufener Code: Meldung, Felder leeren, schuetteln.
     *
     * Das Leeren und das Schuetteln macht das Skript
     * (resources/js/modules/code-input.js) -- es kennt die sechs Kaestchen, der
     * Zustand hier kennt nur den zusammengesetzten Wert.
     */
    private function failCode(string $message): void
    {
        $this->code = '';
        $this->addError('code', $message);
        $this->dispatch('code-input-reset');
    }

    /**
     * Falsch oder abgelaufen?
     *
     * Der Dienst sagt zu beidem nur "nein" -- die Unterscheidung dort waere
     * ein Hinweis fuers Raten. Hier laesst sie sich trotzdem treffen, ohne
     * etwas zu verraten: Das Alter des eigenen offenen Versuchs steht am
     * eigenen Konto. Ist es ueber der Lebensdauer eines Codes
     * (`phone_verification.code_ttl_minutes`), war der Code abgelaufen -- und
     * dann hilft "versuch es noch einmal" nicht, sondern nur ein neuer Code.
     *
     * Das Angebot dazu steht von selbst daneben: Nach zehn Minuten ist die
     * Sperrfrist des erneuten Versands lange abgelaufen, der Zaehler steht auf
     * null und die Zeile zeigt den Link.
     */
    private function codeFailureMessage(PhoneVerificationService $verification, User $user): string
    {
        $attempt = $verification->pendingAttempt($user);
        $ttlMinutes = max(1, (int) config('phone_verification.code_ttl_minutes', 10));

        $expired = $attempt instanceof PhoneVerificationAttempt
            && $attempt->created_at !== null
            && $attempt->created_at->copy()->addMinutes($ttlMinutes)->isPast();

        return $expired
            ? __('portal.onboarding.phone.errors.code_expired')
            : __('portal.onboarding.phone.errors.code_wrong');
    }

    /**
     * Die Wartezeit setzen -- und den Schluessel des Zaehlerblocks mitziehen.
     */
    private function setResendIn(int $seconds): void
    {
        $this->resendIn = max(0, $seconds);
        $this->resendTick++;
    }

    private function secondsUntilResend(PhoneVerificationService $verification): int
    {
        $user = auth()->user();

        if ($user === null) {
            return 0;
        }

        $allowedAt = $verification->resendAllowedAt($user);

        return $allowedAt === null
            ? 0
            : max(0, (int) ceil(now()->diffInSeconds($allowedAt, absolute: true)));
    }

    /**
     * Nach einem Neuladen ist $resendCount wieder null, der Anruf-Ausweg soll
     * aber nicht verschwinden.
     *
     * Dass es fuer diese Nummer schon eine SMS gab, sagt der Dienst. Das
     * allein genuegt aber nicht -- sonst stuende der Anruf schon beim ersten
     * Laden des Code-Screens daneben, und der teure Weg waere von Anfang an
     * gleichberechtigt. Zusaetzlich muss die Sperrfrist abgelaufen sein: Dann
     * hat der Nutzer die Minute abgewartet, und genau in diesem Fall ist der
     * Anruf gemeint.
     */
    private function callAvailableFromHistory(PhoneVerificationService $verification): void
    {
        $user = auth()->user();

        if ($user === null || $this->phoneE164 === null || $this->resendIn > 0) {
            return;
        }

        if ($verification->callChannelAvailable($user, $this->phoneE164)) {
            $this->resendCount = max($this->resendCount, 1);
        }
    }
}
