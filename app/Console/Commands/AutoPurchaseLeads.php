<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AutoLeadPurchaseService;
use Illuminate\Console\Command;

/**
 * FB-056: Kauft passende Leads fuer Kaeufer mit aktivem Autokauf.
 *
 * Duenner Aufruf -- die Fachlogik steht im AutoLeadPurchaseService. Der
 * Bericht am Ende sagt je Kaufprofil, warum gekauft wurde oder eben nicht;
 * mit --dry-run laeuft dieselbe Auswahl, ohne etwas zu kaufen.
 */
class AutoPurchaseLeads extends Command
{
    /**
     * Die Gruende des Dienstes im Klartext -- "0 gekauft" allein sagt nicht,
     * woran es lag.
     *
     * @var array<string, string>
     */
    private const REASONS = [
        AutoLeadPurchaseService::REASON_BOUGHT => 'gekauft',
        AutoLeadPurchaseService::REASON_NOT_APPROVED => 'Kaeufer nicht freigeschaltet',
        AutoLeadPurchaseService::REASON_DAILY_LIMIT => 'Tageslimit erreicht',
        AutoLeadPurchaseService::REASON_NO_MATCH => 'kein passender Lead',
        AutoLeadPurchaseService::REASON_NO_FUNDS => 'Guthaben reicht nicht',
    ];

    protected $signature = 'app:auto-purchase-leads {--dry-run : Nur zeigen, was gekauft wuerde}';

    protected $description = 'Buy matching leads for buyers who have automatic purchasing enabled.';

    public function __construct(private readonly AutoLeadPurchaseService $autoPurchases)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $bought = $this->autoPurchases->run($dryRun);

        $report = $this->autoPurchases->report();

        if ($report === []) {
            $this->warn('Kein Kaufprofil mit aktivem Autokauf.');

            return self::SUCCESS;
        }

        $this->table(
            ['Profil', 'Kaeufer', 'Treffer', 'Gekauft', 'Ergebnis'],
            array_map(static fn (array $row): array => [
                $row['profile_id'],
                $row['tenant'],
                $row['matched'],
                $row['bought'],
                self::REASONS[$row['reason']] ?? $row['reason'],
            ], $report),
        );

        $this->info(sprintf(
            $dryRun ? '%d Lead(s) wuerden automatisch gekauft.' : '%d Lead(s) automatisch gekauft.',
            $bought,
        ));

        return self::SUCCESS;
    }
}
