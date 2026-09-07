<?php

declare(strict_types=1);

namespace App\Listeners\Webhooks;

use App\Constants\WebhookEvent;
use App\Events\Funnel\FunnelPublished;
use App\Services\WebhookDispatcher;

/**
 * Meldet eine neue Veroeffentlichung an die Webhooks des Funnels (FB-030e).
 */
class DispatchFunnelPublishedWebhook
{
    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(FunnelPublished $event): void
    {
        $this->dispatcher->dispatch($event->funnel, WebhookEvent::FUNNEL_PUBLISHED, [
            'version' => $event->version->version,
            'note' => $event->version->note,
            'published_at' => $event->version->published_at?->toIso8601String(),
        ]);
    }
}
