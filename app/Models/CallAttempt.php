<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\CallAttemptOutcome;
use App\Constants\CallAttemptStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScopes;
use Database\Factories\CallAttemptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein Anrufversuch eines Kaeufers bei einem gekauften Lead (FB-082).
 *
 * Entsteht und veraendert sich ausschliesslich in App\Services\CallService --
 * entweder beim Start des Anrufs oder im Rueckruf von Twilio. Deshalb steht
 * alles, was das Ergebnis belegt, in $guarded.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $lead_purchase_id
 * @property int $lead_id
 * @property int $user_id
 * @property string $caller_number
 * @property string $lead_number
 * @property CallAttemptStatus $status
 * @property string|null $provider_call_sid
 * @property string|null $provider_dial_sid
 * @property string|null $dial_status
 * @property int|null $duration_seconds
 * @property string|null $answered_by
 * @property CallAttemptOutcome|null $outcome
 * @property string|null $ignore_reason
 * @property array<string, mixed>|null $provider_payload
 * @property Carbon|null $started_at
 * @property Carbon|null $answered_at
 * @property Carbon|null $ended_at
 */
class CallAttempt extends Model
{
    /** @use HasFactory<CallAttemptFactory> */
    use BelongsToTenant, HasFactory, HasUlids;

    /**
     * Die Rufnummer des Leads verlaesst den Versuch nie ueber eine
     * Serialisierung (FB-085).
     *
     * `lead_number` steht hier, weil der Versuch dem Kaeufer gehoert und in
     * seinen Ansichten auftaucht: Ein toArray() im Portal wuerde sonst genau
     * die Nummer ausliefern, die der Kaeufer vor der Abrechnung nicht bekommen
     * soll. Gewaehlt wird serverseitig in der Bridge -- den Wert braucht dort
     * niemand aus einer Serialisierung.
     *
     * @var list<string>
     */
    protected $hidden = [
        'lead_number',
    ];

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'uuid',
        'status',
        'provider_call_sid',
        'provider_dial_sid',
        'dial_status',
        'duration_seconds',
        'answered_by',
        'outcome',
        'ignore_reason',
        'provider_payload',
        'started_at',
        'answered_at',
        'ended_at',
    ];

    /**
     * Nur `uuid` ist eine ULID -- der Primaerschluessel bleibt fortlaufend.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<LeadPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(LeadPurchase::class, 'lead_purchase_id');
    }

    /**
     * Der Lead zum Versuch -- bewusst ohne Mandanten-Scopes (FB-092).
     *
     * Der Lead gehoert dem Betreiber, der Versuch dem Kaeufer: `leads.tenant_id`
     * und `call_attempts.tenant_id` zeigen also auf verschiedene Workspaces.
     * Mit aktivem Scope liefe die Beziehung im Kaeufer-Panel gegen den
     * Kaeufer-Workspace und gaebe null zurueck -- AttemptClassifier haelt den
     * Lead dann fuer geschlossen und bewertet jeden Versuch als
     * failed_ignored/lead_closed, LeadResolver steigt still aus.
     *
     * Die Mandantentrennung haengt am Versuch selbst: Wer nur die eigenen
     * Versuche abfragt, kommt auch nur an die dazugehoerigen Leads. Ebenso
     * geloest wie bei LeadPurchase::lead().
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes(TenantScopes::names());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Gueltige erfolglose Versuche, aelteste zuerst.
     *
     * Genau die Menge, die das Regelwerk zaehlt (FB-083): bewertet, erfolglos
     * und nicht verworfen. Die Reihenfolge nach `started_at` ist Teil der
     * Aussage -- die Spanne zwischen erstem und letztem Versuch entscheidet
     * mit ueber `unreachable_min_days`.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeValidFailed(Builder $query): void
    {
        $query->where('outcome', CallAttemptOutcome::FAILED_VALID)->oldest('started_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CallAttemptStatus::class,
            'outcome' => CallAttemptOutcome::class,
            'duration_seconds' => 'integer',
            'provider_payload' => 'array',
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
