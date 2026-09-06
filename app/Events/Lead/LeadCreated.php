<?php

declare(strict_types=1);

namespace App\Events\Lead;

use App\Models\Lead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Aus einer abgeschlossenen Sitzung ist ein Lead entstanden (FB-031).
 *
 * Der Lead steht dabei im Zustand `neu` und ist noch nicht kaufbar. Die
 * Pruefung, die ihn nach `verfuegbar` bringt oder als Dublette aussortiert,
 * haengt sich hier ein (FB-033) -- ebenso Webhooks (FB-030e). Das Ereignis
 * feuert erst, wenn Lead und Antworten geschrieben sind.
 */
class LeadCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Lead $lead) {}
}
