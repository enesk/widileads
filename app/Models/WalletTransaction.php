<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\WalletTransactionType;
use App\Exceptions\ImmutableLedgerException;
use App\Models\Builders\WalletTransactionQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Eine unveraenderliche Buchung im Wallet-Ledger (LP-WALLET-004).
 *
 * Buchungen entstehen ausschliesslich im WalletService (LP-WALLET-005) -- nur
 * dort werden Deckung, Vorzeichen und Saldenfortschreibung in einer Transaktion
 * gehalten. Danach werden sie nie wieder angefasst: Aendern und Loeschen werfen
 * eine ImmutableLedgerException, sowohl ueber das Model als auch ueber den
 * Query-Builder (gleiches Muster wie AuditLog und LeadStateLog).
 *
 * Es gibt kein `updated_at`: Eine Zeitspalte, die eine Aenderung anzeigen
 * koennte, waere eine Einladung, doch eine zu machen.
 *
 * @property int $id
 * @property int $wallet_id
 * @property WalletTransactionType $type
 * @property int $amount_cents Vorzeichenbehaftet, siehe WalletTransactionType::sign()
 * @property int $balance_after_cents
 * @property int $reserved_after_cents
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $idempotency_key
 * @property string $description
 * @property array<string, mixed>|null $meta
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
class WalletTransaction extends Model
{
    /**
     * Append-only: Eine Buchung wird nie aktualisiert, deshalb gibt es keine
     * Spalte updated_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'type',
        'amount_cents',
        'balance_after_cents',
        'reserved_after_cents',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'description',
        'meta',
        'created_by',
    ];

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Beleg der Buchung -- der Leadkauf, die Bestellung der Aufladung, die
     * Auszahlungsanforderung.
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    /**
     * Der Admin, der eine manuelle Korrektur gebucht hat. Sonst null.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw ImmutableLedgerException::forUpdate();
    }

    public function delete(): bool
    {
        throw ImmutableLedgerException::forDeletion();
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder($query): WalletTransactionQueryBuilder
    {
        return new WalletTransactionQueryBuilder($query);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'reserved_after_cents' => 'integer',
            'reference_id' => 'integer',
            'created_by' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Faengt auch Wege ab, die update()/delete() umgehen, etwa save() auf
        // einem geladenen Eintrag oder Beziehungen mit Kaskade.
        static::updating(function (): void {
            throw ImmutableLedgerException::forUpdate();
        });

        static::deleting(function (): void {
            throw ImmutableLedgerException::forDeletion();
        });
    }
}
