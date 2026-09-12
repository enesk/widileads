<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Models\PostpaidApplication;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Die Ablehnung eines Antrags auf Pay as you go (LP-POSTPAID-006).
 *
 * Bewusst ohne Detailbegruendung: Wer erfaehrt, an welcher Zahl es lag, kann
 * genau diese Zahl herstellen -- und eine Freischaltung, die man sich
 * zusammenkaufen kann, schuetzt vor gar nichts. Die Begruendung steht am
 * Antrag und im Admin-Bereich.
 *
 * Genannt wird stattdessen, was der Kaeufer wissen muss: dass er weiterhin mit
 * Guthaben kauft und wann er erneut beantragen darf.
 */
class PostpaidRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PostpaidApplication $application,
        public Tenant $buyer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('marketplace.wallet.postpaid.mail.rejected.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.postpaid.rejected',
            with: [
                'retryDays' => (string) $this->retryDays(),
            ],
        );
    }

    /**
     * Tage bis zum naechsten moeglichen Antrag. Gerechnet ab der Entscheidung,
     * damit die Zahl auch dann stimmt, wenn die Mail verspaetet zugestellt
     * wird.
     */
    private function retryDays(): int
    {
        $waitDays = (int) config('wallet.postpaid.reapply_after_days');
        $decidedAt = $this->application->decided_at;

        if ($waitDays <= 0 || $decidedAt === null) {
            return max(0, $waitDays);
        }

        $allowedFrom = $decidedAt->copy()->addDays($waitDays);

        return $allowedFrom->isFuture() ? (int) ceil(now()->diffInDays($allowedFrom, true)) : 0;
    }
}
