<?php

declare(strict_types=1);

namespace App\Events\Funnel;

use App\Models\Funnel;
use App\Models\FunnelVersion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Eine neue Fassung eines Funnels wurde veroeffentlicht (FB-030e).
 *
 * Ausgeloest von der Action PublishFunnel (FB-014), damit Webhooks und spaetere
 * Interessenten daran haengen koennen, ohne dass die Action sie kennen muss.
 */
class FunnelPublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Funnel $funnel,
        public readonly FunnelVersion $version,
    ) {}
}
