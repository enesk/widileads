<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LeadPurchaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Der Kaufbeleg eines Leads (FB-054).
 *
 * Er entsteht ausschliesslich in App\Actions\PurchaseLead -- nur dort laufen
 * Reservierung, Guthabenpruefung, Abbuchung und Zustandswechsel in der
 * richtigen Reihenfolge. Er ist zugleich die Grundlage der Entscheidung, wer
 * Kontaktdaten im Klartext sieht (LeadContactResolver, FB-032).
 *
 * `price_cents` wird beim Kauf festgeschrieben und danach nie geaendert: Der
 * Kaeufer hat zu diesem Preis gekauft, auch wenn der Funnelpreis spaeter ein
 * anderer ist (Architekturleitsatz 4).
 *
 * @property int $id
 * @property int $lead_id
 * @property int $buyer_tenant_id
 * @property int $price_cents
 * @property string $currency
 * @property Carbon $purchased_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LeadPurchase extends Model
{
    /** @use HasFactory<LeadPurchaseFactory> */
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'buyer_tenant_id',
        'price_cents',
        'currency',
        'purchased_at',
    ];

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'buyer_tenant_id');
    }

    /**
     * Kaeufe eines Mandanten.
     *
     * Bewusst kein BelongsToTenant: Ein Kaufbeleg gehoert dem Kaeufer, der
     * Lead dagegen dem Betreiber. Ein Mandanten-Scope ueber `tenant_id` waere
     * hier schlicht die falsche Spalte -- deshalb wird ausdruecklich
     * eingeschraenkt, wo es noetig ist.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOfBuyer(Builder $query, Tenant $buyer): void
    {
        $query->where('buyer_tenant_id', $buyer->getKey());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'purchased_at' => 'datetime',
        ];
    }
}
