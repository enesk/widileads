<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\FunnelStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FunnelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Ein Funnel (Klickstrecke / Quiz) eines Betreiber-Mandanten (FB-010).
 *
 * Oeffentlich wird ein Funnel nur ueber public_token adressiert, nie ueber die
 * ID -- deshalb ist der Token auch der Route-Key.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $public_token
 * @property string $name
 * @property string $slug
 * @property FunnelStatus $status
 * @property float|null $lead_price
 * @property int|null $contact_step_position
 * @property array<string, mixed>|null $settings
 * @property int|null $current_version_id
 */
class Funnel extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<FunnelFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'public_token',
        'name',
        'slug',
        'status',
        'lead_price',
        'contact_step_position',
        'settings',
        'current_version_id',
    ];

    /**
     * @return HasMany<FunnelStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(FunnelStep::class)->orderBy('position');
    }

    /**
     * Alle Fragen des Funnels ueber alle Schritte hinweg. Der Feldschluessel ist
     * genau hier eindeutig, nicht nur je Schritt.
     *
     * @return HasMany<FunnelQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(FunnelQuestion::class);
    }

    /**
     * Verzweigungsregeln des Funnels. Ausgewertet werden sie vom StepResolver
     * in FB-012.
     *
     * @return HasMany<FunnelCondition, $this>
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(FunnelCondition::class);
    }

    /**
     * Ergebnis-Screens des Funnels je Punktebereich (Auswahl in FB-013).
     *
     * @return HasMany<FunnelResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(FunnelResult::class);
    }

    /**
     * Erscheinungsbild des Funnels (FB-017). Ohne eigenes Theme gelten die
     * Vorgabewerte aus der Migration.
     *
     * @return HasOne<FunnelTheme, $this>
     */
    public function theme(): HasOne
    {
        return $this->hasOne(FunnelTheme::class);
    }

    /**
     * Alle veroeffentlichten Fassungen, neueste zuerst.
     *
     * @return HasMany<FunnelVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FunnelVersion::class)->orderByDesc('version');
    }

    /**
     * Die Fassung, die aktuell oeffentlich ausgeliefert wird.
     *
     * @return BelongsTo<FunnelVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FunnelVersion::class, 'current_version_id');
    }

    /**
     * Verkaufspreis eines Leads aus diesem Funnel. Ohne eigenen Preis gilt der
     * Standardpreis aus config('funnel.lead.default_price').
     */
    public function effectiveLeadPrice(): float
    {
        if ($this->lead_price !== null) {
            return (float) $this->lead_price;
        }

        return (float) config('funnel.lead.default_price');
    }

    public function isPublished(): bool
    {
        return $this->status->isPubliclyAvailable();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', FunnelStatus::PUBLISHED->value);
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FunnelStatus::class,
            'settings' => 'array',
            'lead_price' => 'decimal:2',
            'contact_step_position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $funnel): void {
            if (($funnel->public_token ?? '') === '') {
                $funnel->public_token = (string) Str::ulid();
            }

            if (($funnel->slug ?? '') === '') {
                $funnel->slug = Str::slug((string) $funnel->name);
            }
        });
    }
}
