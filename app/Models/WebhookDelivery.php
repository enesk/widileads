<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\WebhookDeliveryStatus;
use App\Constants\WebhookEvent;
use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine Zustellung samt Verlauf (FB-030e).
 *
 * @property int $id
 * @property int $webhook_id
 * @property string $event_id
 * @property WebhookEvent $event
 * @property array<string, mixed> $payload
 * @property WebhookDeliveryStatus $status
 * @property int|null $response_code
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $created_at
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'webhook_id',
        'event_id',
        'event',
        'payload',
        'status',
        'response_code',
        'attempts',
        'last_attempt_at',
        'next_attempt_at',
    ];

    /**
     * @return BelongsTo<FunnelWebhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(FunnelWebhook::class, 'webhook_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => WebhookEvent::class,
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }
}
