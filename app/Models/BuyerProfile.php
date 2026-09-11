<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\BuyerNotifyInterval;
use Database\Factories\BuyerProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kaufkriterien eines Kaeufer-Mandanten (FB-051).
 *
 * Das Modell haelt die Kriterien, es wertet sie nicht aus -- das tut
 * App\Marketplace\LeadMatcher. Die Trennung ist Absicht: der Matcher muss ohne
 * Datenbank laufen, damit Marktplatz-Anzeige (FB-053) und Autokauf (FB-056)
 * garantiert dieselbe Antwort bekommen.
 *
 * Ein leeres Filterfeld heisst "keine Einschraenkung".
 *
 * @property int $id
 * @property int $tenant_id
 * @property list<int>|null $funnel_ids
 * @property list<string>|null $postal_prefixes
 * @property array<string, list<string>>|null $answer_filters
 * @property int|null $min_score
 * @property int|null $max_price_cents
 * @property int|null $weekly_budget_cents
 * @property BuyerNotifyInterval|null $notify_interval
 * @property int|null $daily_limit
 * @property bool $auto_buy
 * @property string|null $notify_email
 */
class BuyerProfile extends Model
{
    /** @use HasFactory<BuyerProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'funnel_ids',
        'postal_prefixes',
        'answer_filters',
        'min_score',
        'max_price_cents',
        'daily_limit',
        'weekly_budget_cents',
        'auto_buy',
        'notify_email',
        'notify_interval',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Auf welche Funnels ist der Kaeufer eingeschraenkt? Leer heisst: auf keinen.
     *
     * @return list<int>
     */
    public function funnelIds(): array
    {
        return array_values(array_map('intval', $this->funnel_ids ?? []));
    }

    /**
     * @return list<string>
     */
    public function postalPrefixes(): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $prefix): string => trim((string) $prefix),
            $this->postal_prefixes ?? [],
        ), static fn (string $prefix): bool => $prefix !== ''));
    }

    /**
     * Feldschluessel auf die erlaubten Antworten. Ein Schluessel ohne Werte
     * schraenkt nicht ein und wird verworfen -- er wuerde sonst jeden Lead
     * ausschliessen.
     *
     * @return array<string, list<string>>
     */
    public function answerFilters(): array
    {
        $filters = [];

        foreach ($this->answer_filters ?? [] as $fieldKey => $values) {
            $allowed = array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                (array) $values,
            ), static fn (string $value): bool => $value !== ''));

            if ($allowed !== []) {
                $filters[(string) $fieldKey] = $allowed;
            }
        }

        return $filters;
    }

    /**
     * Profile, die der Autokauf-Job auswertet (FB-056).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAutoBuying(Builder $query): void
    {
        $query->where('auto_buy', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'funnel_ids' => 'array',
            'postal_prefixes' => 'array',
            'answer_filters' => 'array',
            'min_score' => 'integer',
            'max_price_cents' => 'integer',
            'daily_limit' => 'integer',
            'weekly_budget_cents' => 'integer',
            'auto_buy' => 'boolean',
            'notify_interval' => BuyerNotifyInterval::class,
        ];
    }
}
