<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\CallerIdStatus;
use App\Models\CallerId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallerId>
 */
class CallerIdFactory extends Factory
{
    protected $model = CallerId::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_number' => '+4930'.fake()->numerify('#######'),
            'status' => CallerIdStatus::PENDING,
            'validation_sid' => 'VA'.fake()->regexify('[a-f0-9]{32}'),
            'validation_code' => (string) fake()->numberBetween(100000, 999999),
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ];
    }

    public function verified(): self
    {
        return $this->state(fn (): array => [
            'status' => CallerIdStatus::VERIFIED,
            'validation_code' => null,
            'verified_at' => now(),
            'expires_at' => null,
        ]);
    }
}
