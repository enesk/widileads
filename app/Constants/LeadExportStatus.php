<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Zustand eines Lead-Exports (FB-073).
 */
enum LeadExportStatus: string
{
    /** Angefordert, wartet auf die Warteschlange. */
    case PENDING = 'pending';

    /** Die Datei wird gerade geschrieben. */
    case RUNNING = 'running';

    /** Fertig, die Datei steht zum Herunterladen bereit. */
    case READY = 'ready';

    /** Abgebrochen -- der Grund steht am Export. */
    case FAILED = 'failed';

    public function label(): string
    {
        return __('exports.status.'.$this->value);
    }
}
