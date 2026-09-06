<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\BuyerRegistrationStatus;
use Database\Factories\BuyerRegistrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registrierung und Freischaltungsstand eines Kaeufer-Mandanten (FB-050).
 *
 * Ob ein Kaeufer den Marktplatz erreicht, haengt an genau einem Feld: `status`.
 * Es wird nicht von Hand gesetzt, sondern ueber
 * App\Services\BuyerOnboardingService -- nur so entsteht der Audit-Eintrag,
 * der die Entscheidung des Plattform-Admins belegt. Deshalb steht `status` in
 * $guarded und nicht in $fillable.
 *
 * @property int $id
 * @property int $tenant_id
 * @property BuyerRegistrationStatus $status
 * @property string $company_name
 * @property string $contact_name
 * @property string $contact_email
 * @property string|null $contact_phone
 * @property string|null $broker_register_number
 * @property string $vat_id
 * @property Carbon $av_accepted_at
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BuyerRegistration extends Model
{
    /** @use HasFactory<BuyerRegistrationFactory> */
    use HasFactory;

    /**
     * Geschuetzt ist alles, was die Freischaltung ausmacht: Der Zustand und
     * seine Belege entstehen ausschliesslich im BuyerOnboardingService.
     *
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'status',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Der Plattform-Admin, der entschieden hat -- oder null, solange die
     * Entscheidung aussteht.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Darf dieser Kaeufer den Marktplatz erreichen?
     */
    public function isApproved(): bool
    {
        return $this->status->grantsMarketplaceAccess();
    }

    /**
     * Steht die Entscheidung des Plattform-Admins noch aus?
     */
    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    /**
     * Offene Registrierungen -- die Pruefliste des Plattform-Admins.
     *
     * @param  Builder<$this>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', BuyerRegistrationStatus::PENDING->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BuyerRegistrationStatus::class,
            'av_accepted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
