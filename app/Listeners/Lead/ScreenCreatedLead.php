<?php

declare(strict_types=1);

namespace App\Listeners\Lead;

use App\Events\Lead\LeadCreated;
use App\Services\LeadScreeningService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * FB-033: Prueft jeden neuen Lead in der Warteschlange.
 *
 * Bewusst nicht im Request: Die Pruefung soll die oeffentliche Strecke nicht
 * aufhalten -- der Endkunde hat seine Anfrage abgeschickt, alles Weitere ist
 * nicht sein Problem. Faellt die Warteschlange aus, bleibt der Lead in `neu`
 * liegen und wird nachgeholt, statt verloren zu gehen.
 *
 * Duenner Aufruf: die Entscheidung steht im LeadScreeningService.
 */
class ScreenCreatedLead implements ShouldQueue
{
    public function __construct(private readonly LeadScreeningService $screening) {}

    public function handle(LeadCreated $event): void
    {
        $this->screening->screen($event->lead);
    }
}
