<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\BuyerRegistrationStatus;
use App\Constants\TenantType;
use App\Models\BuyerRegistration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuyerRegistration>
 */
class BuyerRegistrationFactory extends Factory
{
    protected $model = BuyerRegistration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory()->state(['type' => TenantType::BUYER]),
            'status' => BuyerRegistrationStatus::PENDING,
            'company_name' => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'contact_email' => $this->faker->unique()->safeEmail(),
            'contact_phone' => '+4930123456',
            'broker_register_number' => null,
            'vat_id' => 'DE'.$this->faker->numerify('#########'),
            'av_accepted_at' => now(),
        ];
    }

    /**
     * Freigeschaltet -- der einzige Zustand mit Marktplatzzugriff.
     */
    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => BuyerRegistrationStatus::ACTIVE,
            'reviewed_at' => now(),
            'reviewed_by' => User::factory()->state(['is_admin' => true]),
        ]);
    }

    public function rejected(string $reason = 'Kein Nachweis der Gewerbeanmeldung.'): static
    {
        return $this->state(fn (): array => [
            'status' => BuyerRegistrationStatus::REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => User::factory()->state(['is_admin' => true]),
            'rejection_reason' => $reason,
        ]);
    }
}
