<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PublicSessionService;
use Illuminate\Console\Command;

/**
 * FB-021: Markiert liegengebliebene Funnel-Sitzungen als abgebrochen.
 *
 * Duenner Aufruf -- die Fachlogik steht im PublicSessionService.
 */
class AbandonStaleFunnelSessions extends Command
{
    protected $signature = 'app:abandon-stale-funnel-sessions';

    protected $description = 'Mark public funnel sessions as abandoned after the configured period of inactivity.';

    public function __construct(private readonly PublicSessionService $publicSessionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $abandoned = $this->publicSessionService->abandonStale();

        $this->info(sprintf('%d Sitzung(en) als abgebrochen markiert.', $abandoned));

        return self::SUCCESS;
    }
}
