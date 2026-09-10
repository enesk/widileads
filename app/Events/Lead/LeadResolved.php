<?php

declare(strict_types=1);

namespace App\Events\Lead;

use App\Constants\LeadContactStatus;
use App\Constants\LeadResolutionReason;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Die Erreichbarkeit eines Leads ist entschieden (FB-083).
 *
 * Ausgeloest ausschliesslich von App\Services\LeadResolver::resolve(), und dort
 * genau einmal je Lead: Der Uebergang von `open` nach `billable` oder
 * `unreachable` passiert unter Sperre, ein zweiter Aufruf faellt vorher heraus.
 *
 * Das Ereignis traegt bewusst nur Kennungen und keine Modelle. An ihm haengen
 * Abrechnung und Gutschrift (FB-085); ein serialisiertes Model waere zum
 * Zeitpunkt der Verarbeitung womoeglich veraltet, eine Kennung nie. Wer den
 * Lead braucht, laedt ihn frisch.
 *
 * ShouldDispatchAfterCommit, weil resolve() in einer Transaktion laeuft: Ein
 * Listener, der den Lead lesen oder eine Rechnungsposition schreiben will,
 * darf erst laufen, wenn der Statuswechsel wirklich in der Datenbank steht.
 */
class LeadResolved implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $leadId  Der entschiedene Lead.
     * @param  int|null  $buyerId  Kaeufer-Workspace, dessen Versuch die Entscheidung ausgeloest hat;
     *                             null bei Fristablauf, der keinem Kaeufer zuzurechnen ist.
     * @param  LeadContactStatus  $contactStatus  Endstand: billable oder unreachable.
     * @param  LeadResolutionReason  $resolvedBy  Warum entschieden wurde.
     */
    public function __construct(
        public int $leadId,
        public ?int $buyerId,
        public LeadContactStatus $contactStatus,
        public LeadResolutionReason $resolvedBy,
    ) {}
}
