<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AutoLeadPurchaseService;
use Illuminate\Console\Command;

/**
 * FB-056: Kauft passende Leads fuer Kaeufer mit aktivem Autokauf.
 *
 * Duenner Aufruf -- die Fachlogik steht im AutoLeadPurchaseService.
 */
class AutoPurchaseLeads extends Command
{
    protected $signature = 'app:auto-purchase-leads';

    protected $description = 'Buy matching leads for buyers who have automatic purchasing enabled.';

    public function __construct(private readonly AutoLeadPurchaseService $autoPurchases)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $bought = $this->autoPurchases->run();

        $this->info(sprintf('%d Lead(s) automatisch gekauft.', $bought));

        return self::SUCCESS;
    }
}
