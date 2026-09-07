<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\WebhookEvent;
use App\Models\Funnel;
use App\Models\FunnelWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelWebhook>
 */
class FunnelWebhookFactory extends Factory
{
    protected $model = FunnelWebhook::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funnel_id' => Funnel::factory(),
            'url' => 'https://crm.example.com/hooks/'.fake()->uuid(),
            'secret' => FunnelWebhook::newSecret(),
            'events' => WebhookEvent::values(),
            'active' => true,
        ];
    }
}
