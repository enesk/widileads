<?php

declare(strict_types=1);

namespace App\Events\Lead;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Models\Lead;
use App\Models\LeadStateLog;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Lead hat seinen Zustand gewechselt (FB-030).
 *
 * Das Ereignis wird ausschliesslich von
 * App\Services\LeadStateService::transition() ausgeloest, nachdem die
 * Zustandsaenderung und ihr Protokolleintrag geschrieben sind. Alle
 * Folgewirkungen -- Webhooks (FB-030e), Benachrichtigungen, Gutschriften --
 * haengen sich hier ein, statt den Zustand selbst anzufassen.
 */
class LeadStateChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  Lead  $lead  Der Lead im bereits gewechselten Zustand.
     * @param  LeadState  $from  Zustand vor dem Wechsel.
     * @param  LeadState  $to  Zustand nach dem Wechsel.
     * @param  LeadTransitionReason  $reason  Warum gewechselt wurde.
     * @param  User|null  $actor  Ausloesender Benutzer; null bei automatischen Uebergaengen.
     * @param  LeadStateLog  $logEntry  Der geschriebene, unveraenderliche Protokolleintrag.
     */
    public function __construct(
        public Lead $lead,
        public LeadState $from,
        public LeadState $to,
        public LeadTransitionReason $reason,
        public ?User $actor,
        public LeadStateLog $logEntry,
    ) {}
}
