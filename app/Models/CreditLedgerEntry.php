<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\CreditLedgerType;
use App\Exceptions\CreditLedgerIsImmutableException;
use App\Models\Builders\CreditLedgerQueryBuilder;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CreditLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Eine unveraenderliche Buchung im Guthabenkonto (FB-052).
 *
 * Buchungen entstehen ausschliesslich in App\Services\CreditLedgerService --
 * nur dort wird die Deckung geprueft und das Vorzeichen gegen die Buchungsart
 * gehalten. Danach werden sie nie wieder angefasst: Aendern und Loeschen werfen
 * eine CreditLedgerIsImmutableException, sowohl ueber das Model als auch ueber
 * den Query-Builder (gleiches Muster wie AuditLog und LeadStateLog).
 *
 * Eine Fehlbuchung wird durch eine Gegenbuchung korrigiert, nicht durch
 * Ueberschreiben.
 *
 * @property int $id
 * @property int $tenant_id
 * @property CreditLedgerType $type
 * @property int $credits
 * @property int|null $amount_cents
 * @property string $currency ISO 4217, gilt fuer die ganze Buchung (FB-052a)
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property Carbon|null $created_at
 */
class CreditLedgerEntry extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CreditLedgerEntryFactory> */
    use HasFactory;

    /**
     * Die Tabelle heisst bewusst im Singular: sie ist ein Journal, keine
     * Sammlung gleichrangiger Datensaetze (so auch im Datenmodell, Teil 2).
     */
    protected $table = 'credit_ledger';

    /**
     * Eine Buchung wird nie aktualisiert, deshalb gibt es keine Spalte
     * updated_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'type',
        'credits',
        'amount_cents',
        'currency',
        'reference_type',
        'reference_id',
    ];

    /**
     * Beleg der Buchung -- die Bestellung, der Lead-Kauf oder die Reklamation.
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw CreditLedgerIsImmutableException::forUpdate();
    }

    public function delete(): bool
    {
        throw CreditLedgerIsImmutableException::forDeletion();
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder($query): CreditLedgerQueryBuilder
    {
        return new CreditLedgerQueryBuilder($query);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditLedgerType::class,
            'credits' => 'integer',
            'amount_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Faengt auch Wege ab, die update()/delete() umgehen, etwa save() auf
        // einem geladenen Eintrag oder Beziehungen mit Kaskade.
        static::updating(function (): void {
            throw CreditLedgerIsImmutableException::forUpdate();
        });

        static::deleting(function (): void {
            throw CreditLedgerIsImmutableException::forDeletion();
        });
    }
}
