<?php

declare(strict_types=1);

namespace App\Mail\Onboarding;

use App\Constants\OnboardingMailKey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Der gemeinsame Rumpf der vier Onboarding-Mails (LP-ONBOARD-014).
 *
 * Alles, was alle vier teilen, steht hier: Betreff aus dem Sprachzweig,
 * HTML-Rumpf und Nur-Text-Fassung aus derselben Namensregel, der Abmeldelink.
 * Die Unterklassen liefern nur noch ihren Schluessel und ihre eigenen Werte.
 *
 * **Bewusst ohne ShouldQueue**, anders als die uebrigen Mailables im Projekt:
 * Diese Mails liegen schon in der Queue -- der SendOnboardingMail-Job stellt
 * sie zu und setzt `sent_at` erst danach. Waere die Mail selbst nochmal
 * eingereiht, waere der Job sofort fertig, die Zeile als versendet markiert
 * und ein Fehler des Mailproviders unsichtbar.
 */
abstract class OnboardingMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $workspace,
        public User $recipient,
    ) {}

    /**
     * Welche der vier Mails das hier ist.
     */
    abstract public function key(): OnboardingMailKey;

    /**
     * Die Werte, die nur diese eine Mail braucht.
     *
     * @return array<string, mixed>
     */
    abstract protected function payload(): array;

    /**
     * Betreff ohne Ausrufezeichen und in der Du-Form -- beides steht im
     * Sprachzweig und nicht hier.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) __($this->key()->langKey().'.subject'),
        );
    }

    public function content(): Content
    {
        $key = $this->key();

        return new Content(
            view: $key->view(),
            // Jede Mail dieser Strecke hat eine Nur-Text-Fassung. Sie ist
            // nicht nur Barrierefreiheit: Eine HTML-Mail ohne Textteil
            // bekommt bei vielen Filtern Punkte als Spam.
            text: $key->view().'-text',
            with: array_merge([
                'lang' => $key->langKey(),
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ], $this->payload()),
        );
    }

    /**
     * Der Abmeldekopf fuer Postfaecher, die ihn selbst anbieten.
     *
     * Ohne One-Click (das braeuchte ein POST-Ziel): Der Link fuehrt auf die
     * Bestaetigungsseite, auf der die Abmeldung mit einem Klick passiert.
     */
    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
        ]);
    }

    /**
     * Der Abmeldelink -- signiert und nur fuer diese Strecke.
     *
     * Signiert, weil die Adresse ohne Anmeldung funktionieren muss: Wer sich
     * abmelden will, soll sich dafuer nicht einloggen. Ohne Ablauf, weil ein
     * abgelaufener Abmeldelink in einer alten Mail genau den aergert, der
     * schon genervt ist.
     */
    protected function unsubscribeUrl(): string
    {
        return URL::signedRoute('onboarding-mails.unsubscribe', [
            'user' => $this->recipient->uuid,
        ]);
    }
}
