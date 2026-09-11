<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\BuyerLeadFeedback;
use App\Constants\PurchaseStatus;
use App\Models\Scopes\TenantScopes;
use Database\Factories\LeadPurchaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property int $seller_tenant_id
 * @property int $price_cents
 * @property string $commission_percent Provisionssatz, festgeschrieben zum Kaufzeitpunkt
 * @property int $commission_cents
 * @property int $seller_net_cents
 * @property PurchaseStatus $status
 * @property string $currency
 * @property Carbon $purchased_at
 * @property Carbon $reserved_at
 * @property Carbon|null $captured_at
 * @property Carbon|null $released_at
 * @property Carbon|null $refunded_at
 * @property BuyerLeadFeedback|null $buyer_feedback
 * @property Carbon|null $buyer_feedback_at
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
        'seller_tenant_id',
        'price_cents',
        'commission_percent',
        'commission_cents',
        'seller_net_cents',
        'status',
        'currency',
        'purchased_at',
        'reserved_at',
        'captured_at',
        'released_at',
        'refunded_at',
        'buyer_feedback',
        'buyer_feedback_at',
    ];

    /**
     * Der gekaufte Lead.
     *
     * Ohne Mandanten-Scope, und das ist keine Bequemlichkeit: Der Lead gehoert
     * dem Betreiber, der Kaufbeleg dem Kaeufer. Im Kaeufer-Kontext liefe die
     * Beziehung sonst gegen `leads.tenant_id` des Kaeufers und gaebe immer null
     * zurueck -- ein Kaeufer saehe seine eigenen Kaeufe als leere Zeilen.
     *
     * Die Mandantentrennung haengt hier am Kaufbeleg selbst: Wer nur seine
     * eigenen Belege abfragt (siehe scopeOfBuyer), kommt auch nur an die Leads,
     * die er gekauft hat.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes(TenantScopes::names());
    }

    /**
     * Die Reklamation zu diesem Kauf, falls es eine gibt (FB-058).
     *
     * @return HasOne<LeadComplaint, $this>
     */
    public function complaint(): HasOne
    {
        return $this->hasOne(LeadComplaint::class);
    }

    /**
     * Die Anrufversuche dieses Kaeufers bei dem gekauften Lead (FB-082),
     * neueste zuerst.
     *
     * Bewusst am Kaufbeleg und nicht am Lead: Bei einem geteilten Lead
     * (FB-055) sieht jeder Kaeufer nur seine eigenen Versuche. Die
     * Mandantentrennung kommt zusaetzlich ueber den Tenant-Scope des
     * Versuchs.
     *
     * @return HasMany<CallAttempt, $this>
     */
    public function callAttempts(): HasMany
    {
        return $this->hasMany(CallAttempt::class)->latest('created_at');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'buyer_tenant_id');
    }

    /**
     * Der verkaufende Betreiber-Mandant, dem der Lead gehoert (LP-WALLET-004).
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'seller_tenant_id');
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
            'commission_cents' => 'integer',
            'seller_net_cents' => 'integer',
            'status' => PurchaseStatus::class,
            'purchased_at' => 'datetime',
            'reserved_at' => 'datetime',
            'captured_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
            'buyer_feedback' => BuyerLeadFeedback::class,
            'buyer_feedback_at' => 'datetime',
        ];
    }
}
