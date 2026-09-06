<?php

declare(strict_types=1);

namespace App\Actions;

use App\Funnel\Snapshots\SnapshotBuilder;
use App\Models\Funnel;
use App\Models\Tenant;
use App\Services\FunnelStructureWriter;

/**
 * Tiefe Kopie eines Funnels (FB-018).
 *
 * Kopiert wird ueber das Snapshot-Format: der SnapshotBuilder liest den
 * Quell-Funnel samt Schritten, Fragen, Optionen, Regeln und Ergebnissen, der
 * FunnelStructureWriter schreibt daraus einen neuen Entwurf. Das ist derselbe
 * Weg, den auch der Vorlagen-Import aus FB-019 nimmt -- und er sorgt
 * nebenbei dafuer, dass die Kopie keine ID des Originals uebernimmt: das Format
 * kennt nur Positionen und Feldschluessel.
 *
 * Die Kopie ist immer ein Entwurf mit eigenem oeffentlichen Token. Sie erbt
 * keine Versionen: der Verlauf gehoert zum Original.
 */
class DuplicateFunnel
{
    public function __construct(
        private readonly SnapshotBuilder $snapshotBuilder,
        private readonly FunnelStructureWriter $structureWriter,
    ) {}

    /**
     * @param  Tenant|null  $target  Zielmandant; ohne Angabe bleibt die Kopie beim Eigentuemer.
     */
    public function handle(Funnel $funnel, ?Tenant $target = null): Funnel
    {
        $tenant = $target ?? $funnel->tenant()->withoutGlobalScopes()->firstOrFail();

        return $this->structureWriter->write(
            $this->snapshotBuilder->build($funnel),
            $tenant,
            [
                // Der Snapshot traegt den aufgeloesten Preis (Funnel-Preis oder
                // Konfigurationswert). Fuer die Kopie zaehlt der rohe Wert -- sonst
                // wuerde aus einem geerbten Preis stillschweigend ein fester.
                'lead_price' => $funnel->getRawOriginal('lead_price'),
                'settings' => $funnel->settings,
            ],
        );
    }
}
