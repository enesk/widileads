<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\FunnelVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelVersion>
 */
class FunnelVersionFactory extends Factory
{
    protected $model = FunnelVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'version' => 1,
            'snapshot' => [
                'funnel' => ['public_token' => (string) str()->ulid(), 'name' => 'Testfunnel'],
                'steps' => [],
                'conditions' => [],
                'results' => [],
            ],
            'published_at' => now(),
            'published_by' => null,
        ];
    }
}
