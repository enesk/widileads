<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Der Antrag eines Kaeufers auf Freischaltung von Pay as you go
 * (LP-POSTPAID-003).
 *
 * Der Antrag ist der Beleg einer Entscheidung und nicht nur ein Schalter:
 * `eligibility_snapshot` haelt fest, welche Zahlen zum Zeitpunkt der
 * Antragstellung galten (LP-POSTPAID-006). Ohne diesen Schnappschuss liesse
 * sich eine spaetere Ablehnung oder ein Zahlungsausfall nicht mehr
 * nachvollziehen, weil die Werte bis dahin weitergelaufen sind.
 *
 * Wie Zahlungsmittel und Settlements haengt der Antrag am Wallet: Der
 * Kreditrahmen, um den es geht, steht dort.
 *
 * @property int $id
 * @property int $wallet_id
 * @property string $status
 * @property array<string, mixed> $eligibility_snapshot
 * @property Carbon $requested_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wallet $wallet
 */
class PostpaidApplication extends Model
{
    /** Eingereicht, der Admin hat noch nicht entschieden. */
    public const STATUS_REQUESTED = 'requested';

    /** Freigegeben; Kreditrahmen und Modus stehen am Wallet. */
    public const STATUS_APPROVED = 'approved';

    /** Abgelehnt; ein neuer Antrag ist spaeter moeglich. */
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'wallet_id',
        'status',
        'eligibility_snapshot',
        'requested_at',
        'decided_at',
        'decided_by',
        'note',
    ];

    /**
     * Der Geldtopf, fuer den der Rahmen beantragt wird.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Der Admin, der entschieden hat. Vor der Entscheidung null.
     *
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Der antragstellende Kaeufer. Ueber das Wallet, siehe Klassenkommentar.
     */
    public function buyer(): ?Tenant
    {
        return $this->wallet?->owner;
    }

    /**
     * Wartet dieser Antrag noch auf eine Entscheidung?
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }

    /**
     * Noch nicht entschiedene Antraege -- die Arbeitsliste des Admins.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_REQUESTED);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'eligibility_snapshot' => 'array',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'decided_by' => 'integer',
        ];
    }
}
