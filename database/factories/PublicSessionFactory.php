<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FunnelVersion;
use App\Models\PublicSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PublicSession>
 */
class PublicSessionFactory extends Factory
{
    protected $model = PublicSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_version_id' => FunnelVersion::factory(),
            'token' => (string) Str::ulid(),
            'answers' => [],
            'current_step' => 1,
            'started_at' => now(),
            'last_activity_at' => now(),
            'completed_at' => null,
            'abandoned_at' => null,
        ];
    }

    public function inactiveFor(int $minutes): static
    {
        return $this->state(fn (): array => [
            'started_at' => now()->subMinutes($minutes),
            'last_activity_at' => now()->subMinutes($minutes),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['completed_at' => now()]);
    }
}
