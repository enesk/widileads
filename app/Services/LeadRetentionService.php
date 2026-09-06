<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Models\Lead;
use Illuminate\Support\Carbon;

/**
 * Aufbewahrungsfrist von Leads (FB-037).
 *
 * Ein Lauf besteht aus zwei Schritten, in dieser Reihenfolge:
 *
 * 1. Leads, die nie verkauft wurden und laenger als
 *    config('funnel.lead.retention_days') im Marktplatz stehen, gehen nach
 *    `abgelaufen`. Der Wechsel laeuft ausschliesslich ueber
 *    LeadStateService::transition() (Architekturleitsatz 2) und hinterlaesst
 *    damit denselben Protokolleintrag wie jeder andere Zustandswechsel.
 * 2. Alle Leads in einem Endzustand, die aelter als die Frist sind, verlieren
 *    ihren Personenbezug. Die im ersten Schritt abgelaufenen Leads sind dabei,
 *    weil `abgelaufen` ein Endzustand ist.
 *
 * Der Lauf ist wiederholbar: bereits anonymisierte Leads werden uebergangen,
 * ein zweiter Aufruf am selben Tag aendert nichts mehr.
 */
class LeadRetentionService
{
    public function __construct(
        private readonly LeadStateService $leadStateService,
        private readonly LeadAnonymizer $leadAnonymizer,
    ) {}

    /**
     * Fuehrt beide Schritte aus.
     *
     * @return array{expired: int, anonymized: int}
     */
    public function apply(): array
    {
        return [
            'expired' => $this->expireUnsoldLeads(),
            'anonymized' => $this->anonymizeLeadsPastRetention(),
        ];
    }

    /**
     * Schritt 1: `verfuegbar` -> `abgelaufen` nach Ablauf der Frist.
     *
     * @return int Anzahl der abgelaufenen Leads.
     */
    public function expireUnsoldLeads(): int
    {
        $expired = 0;

        Lead::query()
            ->where('lead_state', LeadState::VERFUEGBAR)
            ->where('created_at', '<=', $this->retentionCutoff())
            ->chunkById($this->chunkSize(), function ($leads) use (&$expired): void {
                foreach ($leads as $lead) {
                    $this->leadStateService->transition(
                        $lead,
                        LeadState::ABGELAUFEN,
                        LeadTransitionReason::RETENTION_ELAPSED,
                    );

                    $expired++;
                }
            });

        return $expired;
    }

    /**
     * Schritt 2: Personenbezug aller Leads entfernen, die in einem Endzustand
     * stehen und aelter als die Frist sind.
     *
     * @return int Anzahl der anonymisierten Leads.
     */
    public function anonymizeLeadsPastRetention(): int
    {
        $anonymized = 0;

        Lead::query()
            ->whereIn('lead_state', LeadState::finalStates())
            ->whereNull('anonymized_at')
            ->where('created_at', '<=', $this->retentionCutoff())
            ->chunkById($this->chunkSize(), function ($leads) use (&$anonymized): void {
                foreach ($leads as $lead) {
                    if ($this->leadAnonymizer->anonymize($lead)) {
                        $anonymized++;
                    }
                }
            });

        return $anonymized;
    }

    /**
     * Aelter als dieser Zeitpunkt heisst: Aufbewahrungsfrist abgelaufen.
     *
     * Gerechnet wird ab `created_at`, also ab dem Zeitpunkt der Erhebung der
     * Daten -- nicht ab dem Verkauf oder dem Eintritt in den Endzustand.
     */
    private function retentionCutoff(): Carbon
    {
        return now()->subDays((int) config('funnel.lead.retention_days'));
    }

    private function chunkSize(): int
    {
        return (int) config('funnel.lead.retention_chunk_size');
    }
}
