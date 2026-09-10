<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\ComplaintStatus;
use App\Constants\LeadState;
use App\Models\Scopes\TenantScopes;
use Database\Factories\LeadComplaintFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Die Reklamation eines gekauften Leads (FB-058).
 *
 * Antraege entstehen und werden entschieden ausschliesslich in
 * App\Services\LeadComplaintService -- nur dort laufen Zustandswechsel und
 * Gutschrift in der richtigen Reihenfolge. Deshalb steht `status` in $guarded.
 *
 * @property int $id
 * @property int $lead_purchase_id
 * @property int $lead_id
 * @property int $buyer_tenant_id
 * @property ComplaintStatus $status
 * @property LeadState $requested_state
 * @property string $reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $decision_note
 * @property Carbon|null $created_at
 */
class LeadComplaint extends Model
{
    /** @use HasFactory<LeadComplaintFactory> */
    use HasFactory;

    /**
     * Der Stand und seine Belege entstehen nur im Dienst.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'decision_note',
    ];

    /**
     * @return BelongsTo<LeadPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(LeadPurchase::class, 'lead_purchase_id');
    }

    /**
     * Ohne Mandanten-Scope: Der Lead gehoert dem Betreiber, die Reklamation dem
     * Kaeufer. Mit Scope kaeme im Kaeufer-Kontext null heraus.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes(TenantScopes::names());
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'buyer_tenant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ComplaintStatus::PENDING->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
            'requested_state' => LeadState::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
