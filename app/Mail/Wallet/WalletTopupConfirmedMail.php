<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Filament\Dashboard\Pages\WalletTopUp;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die Bestaetigung einer Aufladung (LP-WALLET-009).
 *
 * Sie nennt den aufgeladenen Betrag, den neuen Stand und verweist auf den
 * Transaktionsverlauf im Portal. Beide Betraege kommen aus der Buchung selbst
 * (`amount_cents`, `balance_after_cents`) und werden nicht erneut abgefragt:
 * Die Mail wird in der Queue gerendert, und bis dahin kann ein Leadkauf den
 * Saldo schon weiterbewegt haben. Der Stand zum Zeitpunkt der Aufladung ist der
 * Wert, den die Bestaetigung belegen soll.
 */
class WalletTopupConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public WalletTransaction $transaction,
        public User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.top_up.mail.subject', [
                'amount' => $this->formatted((int) $this->transaction->amount_cents),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wallet.topup-confirmed',
            with: [
                'amount' => $this->formatted((int) $this->transaction->amount_cents),
                'balance' => $this->formatted((int) $this->transaction->balance_after_cents),
                // Der Verlauf haengt am Kaeufer-Workspace der Buchung und nicht
                // am gerade aktiven Mandanten: In der Queue gibt es keinen
                // Panel-Kontext.
                'historyUrl' => WalletTopUp::getUrl(panel: 'dashboard', tenant: $this->tenant),
            ],
        );
    }

    /**
     * Centbetrag als deutscher Eurobetrag. Gerechnet wird nirgends mit Euro --
     * hier wird nur angezeigt.
     */
    private function formatted(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, ',', '.').' €';
    }
}
