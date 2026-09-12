<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Models\PostpaidApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meldung an den Betreiber, dass ein Kaeufer Pay as you go beantragt hat
 * (LP-POSTPAID-006).
 *
 * Empfaenger ist ausschliesslich die eigene Betreiberadresse
 * (App\Services\SupportMailbox). Freigegeben wird von Hand -- ohne diese
 * Meldung bliebe der Antrag liegen, bis jemand von sich aus in die Liste
 * schaut.
 *
 * Die Mail nennt nur die drei Zahlen, an denen die Entscheidung meistens
 * haengt. Der vollstaendige Eignungsschnappschuss steht am Antrag im
 * Admin-Bereich: Er ist Beleg und gehoert nicht in ein Postfach.
 */
class PostpaidApplicationReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PostpaidApplication $application,
        public Tenant $buyer,
        public ?User $applicant = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.postpaid.mail.received.subject', [
                'buyer' => (string) $this->buyer->name,
            ]),
        );
    }

    public function content(): Content
    {
        $snapshot = $this->application->eligibility_snapshot ?? [];

        return new Content(
            view: 'emails.postpaid.application-received',
            with: [
                'buyerName' => (string) $this->buyer->name,
                'applicantName' => $this->applicantName(),
                'capturedPurchases' => (string) ($snapshot['captured_purchases'] ?? '—'),
                'accountAge' => __('marketplace.wallet.postpaid.mail.received.account_age_value', [
                    'days' => (string) ($snapshot['account_age_days'] ?? 0),
                ]),
                'balance' => Money::format((int) ($snapshot['balance_cents'] ?? 0)),
                'reference' => '#'.(string) $this->application->getKey(),
            ],
        );
    }

    /**
     * Der antragstellende Benutzer, wie er im Postfach lesbar ist. Ohne
     * benannten Antragsteller -- etwa bei einem Antrag aus einem Kommando --
     * steht hier ein Strich statt einer erfundenen Person.
     */
    private function applicantName(): string
    {
        if ($this->applicant === null) {
            return '—';
        }

        $name = trim((string) $this->applicant->name);

        return $name !== '' ? $name : (string) $this->applicant->email;
    }
}
