<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Constants\WebhookDeliveryStatus;
use App\Constants\WebhookEvent;
use App\Models\FunnelWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_id' => FunnelWebhook::factory(),
            'event_id' => (string) Str::uuid(),
            'event' => WebhookEvent::FUNNEL_PUBLISHED,
            'payload' => ['id' => (string) Str::uuid(), 'event' => 'funnel.published', 'data' => []],
            'status' => WebhookDeliveryStatus::PENDING,
            'attempts' => 0,
        ];
    }
}
