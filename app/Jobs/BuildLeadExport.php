<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Constants\LeadExportStatus;
use App\Models\LeadExport;
use App\Services\LeadExportBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Baut einen angeforderten Lead-Export in der Warteschlange (FB-073).
 *
 * Bewusst nicht im Request: Ein Betreiber mit fuenfstelligen Leadzahlen liefe
 * in einen Timeout, und zwar genau dann, wenn der Export sich lohnt.
 */
class BuildLeadExport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $exportId) {}

    public function handle(LeadExportBuilder $builder): void
    {
        $export = LeadExport::query()->find($this->exportId);

        if ($export === null || $export->status !== LeadExportStatus::PENDING) {
            // Schon gebaut oder geloescht -- ein zweiter Anlauf der
            // Warteschlange schreibt die Datei nicht noch einmal.
            return;
        }

        $builder->build($export);
    }

    public function failed(Throwable $exception): void
    {
        LeadExport::query()->whereKey($this->exportId)->update([
            'status' => LeadExportStatus::FAILED->value,
            // Ohne Ausnahmemeldung: Sie kann Interna enthalten und landet in
            // einer Oberflaeche.
            'failure_reason' => __('exports.errors.failed'),
        ]);
    }
}
