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
 * Meldung an den Kaeufer ueber einen gescheiterten Postpaid-Einzug
 * (LP-POSTPAID-008).
 *
 * Zwei Faelle in einer Mail, weil sie dieselbe Nachricht in zwei Schaerfen
 * ist: Nach dem ersten Fehlschlag steht das Datum des zweiten Versuchs darin
 * und die Bitte, das Zahlungsmittel zu pruefen. Nach dem zweiten ist der
 * Einzug beendet, der Zugang wird zurueckgestuft (LP-POSTPAID-009) und der
 * offene Betrag bleibt bestehen.
 *
 * Nicht dieselbe Mail wie App\Mail\Wallet\LeadSettlementFailed: Jene geht an
 * den Betreiber und meint einen haengenden Kaufbeleg, diese geht an den
 * Kaeufer und meint sein Geld.
 */
class SettlementFailedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  bool  $final  Ob der Einzug endgueltig gescheitert ist.
     */
    public function __construct(
        public Settlement $settlement,
        public Tenant $buyer,
        public bool $final = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __($this->key('subject'), [
                'amount' => Money::format((int) $this->settlement->amount_cents),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet.settlement-charge-failed',
            with: [
                'final' => $this->final,
                'amount' => Money::format((int) $this->settlement->amount_cents),
                'method' => $this->settlement->paymentMethod?->label()
                    ?? __('marketplace.wallet.settlement_notice.method_unknown'),
                'retryDate' => $this->settlement->next_attempt_at?->translatedFormat('d.m.Y'),
                'paymentMethodsUrl' => route('portal.overview', ['tenant' => $this->buyer->uuid]),
            ],
        );
    }

    /**
     * Sprachschluessel des jeweiligen Falls. Beide Zweige liegen unter
     * demselben Ast, damit die Texte im Sprachfile nebeneinander stehen und
     * beim Uebersetzen als Paar gelesen werden.
     */
    private function key(string $suffix): string
    {
        return 'marketplace.wallet.settlement_notice.'
            .($this->final ? 'failed_final' : 'failed_retry')
            .'.'.$suffix;
    }
}
