<?php

declare(strict_types=1);

namespace App\Actions;

use App\Constants\FunnelStatus;
use App\Models\Funnel;

/**
 * Archiviert einen Funnel und holt ihn zurueck (FB-018).
 *
 * Archivieren ist kein Loeschen: Der Funnel bleibt mit allen Versionen und
 * seinen Leads erhalten, wird oeffentlich aber nicht mehr ausgefuellt. Was ein
 * Aufruf der oeffentlichen Adresse dann sieht, entscheidet
 * `config('funnel.runtime_public.show_notice_for_archived')` -- Hinweisseite
 * oder 404 (gebaut in FB-020).
 *
 * Der zurueckgeholte Funnel wird wieder Entwurf und nicht automatisch
 * veroeffentlicht: Wer ihn archiviert hat, soll vor der erneuten Auslieferung
 * noch einmal daraufschauen.
 */
class ArchiveFunnel
{
    public function handle(Funnel $funnel): Funnel
    {
        if ($funnel->status === FunnelStatus::ARCHIVED) {
            return $funnel;
        }

        $funnel->forceFill(['status' => FunnelStatus::ARCHIVED])->save();

        return $funnel;
    }

    public function restore(Funnel $funnel): Funnel
    {
        if ($funnel->status !== FunnelStatus::ARCHIVED) {
            return $funnel;
        }

        $funnel->forceFill(['status' => FunnelStatus::DRAFT])->save();

        return $funnel;
    }
}
