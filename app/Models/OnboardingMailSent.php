<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\OnboardingMailKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Mail der Onboarding-Strecke, die fuer einen Workspace erledigt ist
 * (LP-ONBOARD-014).
 *
 * "Erledigt" heisst versendet ODER uebersprungen -- in beiden Faellen ist die
 * Sache fuer diesen Workspace durch, und genau das sichert der Unique-Index
 * (tenant_id, key) zu. `sent_at` leer und `skipped_reason` leer heisst: Der
 * Versand ist angemeldet, aber noch nicht durch; dieser eine Zustand darf ein
 * Wiederholungsversuch des Jobs nachholen.
 *
 * Geschrieben wird an zwei Stellen: im App\Jobs\Onboarding\SendOnboardingMail
 * (ein Schluessel je Lauf) und einmalig beim Ausrollen von
 * App\Console\Commands\OnboardingMailsSuppress, das die Strecke fuer
 * Bestandskaeufer als erledigt eintraegt.
 *
 * @property int $id
 * @property int $tenant_id
 * @property OnboardingMailKey $key
 * @property Carbon|null $sent_at
 * @property string|null $skipped_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OnboardingMailSent extends Model
{
    protected $table = 'onboarding_mails_sent';

    protected $fillable = [
        'tenant_id',
        'key',
        'sent_at',
        'skipped_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => OnboardingMailKey::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
