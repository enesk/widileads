<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\CallAttemptStatus;
use App\Models\CallAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallAttempt>
 */
class CallAttemptFactory extends Factory
{
    protected $model = CallAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caller_number' => '+4930'.fake()->numerify('#######'),
            'lead_number' => '+4989'.fake()->numerify('#######'),
            'status' => CallAttemptStatus::QUEUED,
            'provider_call_sid' => 'CA'.fake()->regexify('[a-f0-9]{32}'),
            'started_at' => now(),
        ];
    }
}
