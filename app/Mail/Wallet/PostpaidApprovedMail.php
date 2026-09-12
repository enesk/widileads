<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Models\PostpaidApplication;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die Freischaltung von Pay as you go an den Kaeufer (LP-POSTPAID-006).
 *
 * Sie nennt die drei Angaben, nach denen der Kaeufer sonst fragen muesste:
 * seinen Kreditrahmen, den Aufschlag je Lead und die Regeln des Einzugs. Alle
 * drei stehen in config('wallet.postpaid.*') und werden hier eingesetzt, nicht
 * ausgeschrieben -- eine Mail, die einen anderen Aufschlag behauptet als der
 * Marktplatz berechnet, waere schlimmer als keine.
 */
class PostpaidApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PostpaidApplication $application,
        public Tenant $buyer,
        public int $creditLimitCents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.postpaid.mail.approved.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.postpaid.approved',
            with: [
                'creditLimit' => Money::format($this->creditLimitCents),
                'surcharge' => __('marketplace.wallet.postpaid.mail.approved.surcharge_value', [
                    'percent' => $this->surchargePercent(),
                ]),
                'settlement' => __('marketplace.wallet.postpaid.mail.approved.settlement_value', [
                    'threshold' => Money::format((int) config('wallet.postpaid.settlement_threshold_cents')),
                ]),
            ],
        );
    }

    /**
     * Der Aufschlagssatz in deutscher Schreibweise, ohne ueberfluessige
     * Nachkommastelle: "7,5" statt "7.50".
     */
    private function surchargePercent(): string
    {
        $percent = (float) config('wallet.postpaid.surcharge_percent');

        return rtrim(rtrim(number_format($percent, 2, ',', '.'), '0'), ',');
    }
}
