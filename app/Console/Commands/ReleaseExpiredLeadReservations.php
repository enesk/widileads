<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LeadReservationService;
use Illuminate\Console\Command;

/**
 * FB-054: Gibt abgelaufene Lead-Reservierungen zurueck.
 *
 * Duenner Aufruf -- die Fachlogik steht im LeadReservationService.
 */
class ReleaseExpiredLeadReservations extends Command
{
    protected $signature = 'app:release-expired-lead-reservations';

    protected $description = 'Release lead reservations that have exceeded the configured time to live.';

    public function __construct(private readonly LeadReservationService $reservations)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $released = $this->reservations->releaseExpired();

        $this->info(sprintf('%d Reservierung(en) zurueckgegeben.', $released));

        return self::SUCCESS;
    }
}
