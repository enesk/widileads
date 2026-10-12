<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\OnboardingStep;
use Database\Factories\TenantOnboardingStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Der Haken an einem Onboarding-Schritt eines Workspaces (LP-ONBOARD-002).
 *
 * Eine Zeile heisst: dieser Schritt ist erledigt. Es gibt keine Zeile fuer
 * einen offenen Schritt und kein Zuruecknehmen -- wer einen Haken wieder
 * entfernt, nimmt dem Kaeufer eine Mail und einen Trichter-Datenpunkt weg.
 *
 * Geschrieben wird ausschliesslich im App\Services\Onboarding\OnboardingService:
 * nur dort haengen Ereignis, Wizard-Abschluss und das Verwerfen des
 * Fortschritts-Caches an derselben Stelle.
 *
 * @property int $id
 * @property int $tenant_id
 * @property OnboardingStep $step
 * @property Carbon $completed_at
 * @property int|null $completed_by_user_id Handelnder Nutzer; null beim Backfill und bei Listenern
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantOnboardingStep extends Model
{
    /** @use HasFactory<TenantOnboardingStepFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'step',
        'completed_at',
        'completed_by_user_id',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => OnboardingStep::class,
            'completed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }
}
