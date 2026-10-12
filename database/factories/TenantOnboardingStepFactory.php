<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\OnboardingStep;
use App\Constants\TenantType;
use App\Models\Tenant;
use App\Models\TenantOnboardingStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantOnboardingStep>
 */
class TenantOnboardingStepFactory extends Factory
{
    protected $model = TenantOnboardingStep::class;

    /**
     * Ein erledigter Schritt, so wie der OnboardingService ihn schreibt.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory()->state(['type' => TenantType::BUYER]),
            'step' => OnboardingStep::INDUSTRIES_SELECTED,
            'completed_at' => now(),
            'completed_by_user_id' => null,
            'meta' => null,
        ];
    }

    /**
     * Haken fuer einen bestimmten Schritt.
     */
    public function step(OnboardingStep $step): self
    {
        return $this->state(['step' => $step]);
    }
}
