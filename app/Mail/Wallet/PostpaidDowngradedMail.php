<?php

declare(strict_types=1);

namespace App\Mail\Wallet;

use App\Events\Postpaid\PostpaidDowngradeRequested;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meldung ueber das Ende von Pay as you go (LP-POSTPAID-009).
 *
 * Sie nennt drei Dinge und sonst nichts: den offenen Betrag, die Gebuehr und
 * den Weg zum Ausgleich. Kein Drohton -- wer gerade eine Ruecklastschrift
 * hatte, weiss selbst, dass etwas schiefgelaufen ist; ihm zusaetzlich Angst zu
 * machen bringt das Geld nicht schneller herein.
 *
 * Zwei Empfaenger, derselbe Text: Der Kaeufer bekommt sie als Mitteilung, der
 * Betreiber ueber `forOperator()` mit eigenem Betreff zur Kenntnis. So liest
 * ein Rueckruf sich nicht aus zwei verschiedenen Staenden.
 *
 * Der Grund `payment_method_revoked` ist der mildeste Fall: keine Gebuehr,
 * keine Kaufsperre, nur die Bitte, den offenen Betrag binnen der Frist per
 * Aufladung auszugleichen.
 */
class PostpaidDowngradedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Frist (Tage), in der ein offener Betrag nach dem Wegfall des
     * Zahlungsmittels per Aufladung auszugleichen ist.
     */
    public const SETTLE_WITHIN_DAYS = 7;

    /** Geht diese Mail an den Betreiber statt an den Kaeufer? */
    private bool $forOperator = false;

    /**
     * @param  string  $reason  PostpaidDowngradeRequested::REASON_*
     * @param  int  $feeCents  Erhobene Gebuehr, 0 wenn keine
     * @param  int  $openAmountCents  Offener Betrag nach der Rueckstufung
     * @param  bool  $blocked  Ob zusaetzlich die Kaufsperre steht
     */
    public function __construct(
        public Tenant $buyer,
        public string $reason,
        public int $feeCents,
        public int $openAmountCents,
        public bool $blocked,
    ) {}

    /**
     * Dieselbe Mail zur Kenntnis an den Betreiber.
     */
    public function forOperator(): self
    {
        $this->forOperator = true;

        return $this;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->forOperator
                ? __('marketplace.wallet.postpaid.mail.downgraded.operator_subject', [
                    'buyer' => (string) $this->buyer->name,
                ])
                : __('marketplace.wallet.postpaid.mail.downgraded.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.postpaid.downgraded',
            with: [
                'buyerName' => (string) $this->buyer->name,
                'forOperator' => $this->forOperator,
                'reasonText' => __('marketplace.wallet.postpaid.mail.downgraded.reasons.'.$this->reason),
                'fee' => $this->feeCents > 0 ? Money::format($this->feeCents) : null,
                'openAmount' => Money::format($this->openAmountCents),
                'blocked' => $this->blocked,
                'settleWithinDays' => self::SETTLE_WITHIN_DAYS,
                // Nur beim widerrufenen Zahlungsmittel nennt die Mail eine
                // Frist: Dort ist der Ausgleich eine Bitte mit Termin, sonst
                // haelt ohnehin die Kaufsperre den Kaeufer an.
                'showDeadline' => $this->reason === PostpaidDowngradeRequested::REASON_PAYMENT_METHOD_REVOKED,
                'topUpUrl' => route('portal.wallet', ['tenant' => $this->buyer->uuid]),
            ],
        );
    }
}
