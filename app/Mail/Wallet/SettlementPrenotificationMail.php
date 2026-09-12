<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Models\Settlement;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die SEPA-Vorabankuendigung vor einem Postpaid-Einzug (LP-POSTPAID-014).
 *
 * Keine Hoeflichkeit, sondern Pflicht des Zahlungsempfaengers bei der
 * SEPA-Basislastschrift: Betrag und Belastungsdatum muessen dem Zahler vor der
 * Belastung angekuendigt werden. Ohne sie kann der Kaeufer die Abbuchung als
 * unberechtigt zurueckgeben -- und die Ruecklastschrift kostet ihn bei uns
 * Gebuehr und Freischaltung.
 *
 * Genannt werden deshalb alle vier Angaben, ueber die der Kaeufer die Buchung
 * seinem Mandat zuordnen kann: Betrag, Belastungsdatum, die letzten vier
 * Stellen seiner IBAN und die Mandatsreferenz. Die vollstaendige IBAN steht
 * dieser Anwendung nicht zur Verfuegung und gehoert auch nicht in ein
 * Postfach.
 *
 * Verschickt wird sie ausschliesslich vom
 * App\Services\Wallet\SettlementPrenotifier; erst nach erfolgreichem Versand
 * traegt das Settlement `prenotified_at` und darf spaeter belastet werden.
 */
class SettlementPrenotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Settlement $settlement,
        public Tenant $buyer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.prenotification.mail.subject', [
                'amount' => Money::format((int) $this->settlement->amount_cents),
                'date' => $this->chargeDate(),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet.settlement-prenotification',
            with: [
                'amount' => Money::format((int) $this->settlement->amount_cents),
                'chargeDate' => $this->chargeDate(),
                'iban' => $this->maskedAccount(),
                'mandateReference' => $this->mandateReference(),
                'creditorName' => (string) config('wallet.postpaid.creditor_name'),
                'creditorId' => $this->creditorId(),
            ],
        );
    }

    /**
     * Das Konto, von dem eingezogen wird -- nur die letzten vier Stellen. Die
     * vollstaendige IBAN kennt diese Anwendung nicht, sie entsteht im
     * SetupIntent direkt bei Stripe.
     */
    private function maskedAccount(): string
    {
        $last4 = $this->settlement->paymentMethod?->last4;

        return '•••• '.(is_string($last4) && $last4 !== '' ? $last4 : '????');
    }

    /**
     * Das angekuendigte Belastungsdatum in deutscher Schreibweise. Fehlt es,
     * steht hier der heutige Tag -- eine Ankuendigung ohne Datum waere keine.
     * Dass es gesetzt ist, stellt der SettlementPrenotifier vor dem Versand
     * sicher.
     */
    private function chargeDate(): string
    {
        return ($this->settlement->charge_due_at ?? now())->translatedFormat('d.m.Y');
    }

    /**
     * Die Mandatsreferenz, unter der das Lastschriftmandat gefuehrt wird. Das
     * ist die Mandatskennung des Zahlungsanbieters; ohne sie bleibt das Feld
     * leer, statt eine Nummer zu behaupten.
     */
    private function mandateReference(): string
    {
        $reference = $this->settlement->paymentMethod?->provider_mandate_id;

        return is_string($reference) && $reference !== ''
            ? $reference
            : __('marketplace.wallet.prenotification.mail.mandate_unknown');
    }

    /**
     * Glaeubiger-Identifikationsnummer. Hat der Betreiber keine eigene
     * beantragt, gilt die Kennung des Zahlungsdienstleisters -- dieselbe
     * Regel wie im Mandatstext (config/wallet.php).
     */
    private function creditorId(): string
    {
        $own = (string) config('wallet.postpaid.creditor_id');

        return $own !== '' ? $own : (string) config('wallet.postpaid.creditor_id_fallback');
    }
}
