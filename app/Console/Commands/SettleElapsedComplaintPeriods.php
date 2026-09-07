<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LeadComplaintService;
use Illuminate\Console\Command;

/**
 * FB-058: Schliesst Kaeufe ab, deren Reklamationsfrist abgelaufen ist.
 *
 * Duenner Aufruf -- die Fachlogik steht im LeadComplaintService.
 */
class SettleElapsedComplaintPeriods extends Command
{
    protected $signature = 'app:settle-elapsed-complaint-periods';

    protected $description = 'Mark purchased leads as reached once the complaint period has elapsed.';

    public function __construct(private readonly LeadComplaintService $complaints)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $settled = $this->complaints->settleElapsed();

        $this->info(sprintf('%d Lead(s) abgeschlossen.', $settled));

        return self::SUCCESS;
    }
}
