<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LeadRetentionService;
use Illuminate\Console\Command;

/**
 * FB-037: Taeglicher Aufbewahrungslauf.
 *
 * Duenner Aufruf -- die Fachlogik steht in LeadRetentionService.
 */
class ApplyLeadRetention extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:apply-lead-retention';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire unsold leads past the retention period and remove personal data from leads in a final state.';

    public function __construct(
        private readonly LeadRetentionService $leadRetentionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->leadRetentionService->apply();

        $this->info(__('funnel.lead.retention.summary', [
            'expired' => $result['expired'],
            'anonymized' => $result['anonymized'],
            'days' => (int) config('funnel.lead.retention_days'),
        ]));

        return self::SUCCESS;
    }
}
