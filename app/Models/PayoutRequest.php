<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\PayoutStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Die Anforderung einer Auszahlung durch den Verkaeufer (LP-WALLET-010).
 *
 * Sie haengt am Wallet und nicht am Mandanten: Ausgezahlt wird ein Geldtopf,
 * und derselbe Mandant kann als Kaeufer und als Verkaeufer auftreten. Der
 * Verkaeufer ist deshalb `wallet->owner`.
 *
 * Der Datensatz entsteht nur zusammen mit der `payout`-Buchung, die den Betrag
 * sofort vom Wallet nimmt; geschrieben wird er ausschliesslich vom
 * App\Services\Wallet\PayoutService. Er ist damit kein Antrag im Posteingang,
 * sondern der Beleg zu bereits bewegtem Geld.
 *
 * @property int $id
 * @property int $wallet_id
 * @property int $amount_cents Immer positiv; die Ledger-Buchung traegt das Minus
 * @property string|null $iban_last4
 * @property PayoutStatus $status
 * @property Carbon $requested_at
 * @property Carbon|null $processed_at
 * @property int|null $processed_by
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wallet $wallet
 */
class PayoutRequest extends Model
{
    protected $fillable = [
        'wallet_id',
        'amount_cents',
        'iban_last4',
        'status',
        'requested_at',
        'processed_at',
        'processed_by',
        'note',
    ];

    /**
     * Der Geldtopf, aus dem ausgezahlt wird.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Der Admin, der ueberwiesen oder abgelehnt hat. Vor der Entscheidung null.
     *
     * @return BelongsTo<User, $this>
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Der Verkaeufer hinter dieser Anforderung. Ueber das Wallet, weil die
     * Anforderung an einem Geldtopf haengt und nicht am Mandanten.
     */
    public function seller(): ?Tenant
    {
        return $this->wallet?->owner;
    }

    /**
     * Offene Anforderungen: die Arbeitsliste des Admins.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', PayoutStatus::REQUESTED->value);
    }

    /**
     * Anforderungen eines Wallets, neueste zuerst.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForWallet(Builder $query, Wallet $wallet): void
    {
        $query->where('wallet_id', $wallet->getKey())->latest('requested_at');
    }

    /**
     * Die angezeigte Bankverbindung: nur die letzte Vierergruppe, der Rest
     * maskiert. Die vollstaendige IBAN steht verschluesselt am Mandanten und
     * wird in keiner Oberflaeche ausgegeben.
     */
    public function maskedIban(): string
    {
        return $this->iban_last4 === null ? '—' : '•••• '.$this->iban_last4;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'status' => PayoutStatus::class,
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'processed_by' => 'integer',
        ];
    }
}
