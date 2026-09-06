<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\AuditAction;
use App\Models\Lead;
use App\Models\LeadStateLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auskunft und Loeschersuchen nach DSGVO (FB-038).
 *
 * Zwei Rechte einer betroffenen Person, beide ueber ihre E-Mail-Adresse:
 *
 * - **Auskunft** (Art. 15): alles, was zu dieser Adresse gespeichert ist, in
 *   maschinenlesbarer Form -- die Leads mit allen ihren Spalten, ihr
 *   Zustandsprotokoll und ihre Antworten.
 * - **Loeschung** (Art. 17): der Personenbezug verschwindet, der Datensatz
 *   bleibt. Das erledigt LeadAnonymizer aus FB-037, dieselbe Stelle wie der
 *   taegliche Aufbewahrungslauf -- Preis, Zeitstempel und Zustand bleiben also
 *   stehen, damit Abrechnungen der Vergangenheit stimmig bleiben.
 *
 * Beide Vorgaenge landen im Audit-Log, je betroffenem Lead ein Eintrag. Die
 * E-Mail-Adresse steht dabei bewusst NICHT im Eintrag: ein Loeschersuchen darf
 * die Adresse nicht ausgerechnet im Protokoll zuruecklassen. Festgehalten wird,
 * welcher Lead betroffen war -- ueber den Lead ist der Vorgang nachvollziehbar,
 * ohne die Person erneut zu speichern.
 *
 * **Ist-Stand:** `leads` traegt noch keine Kontaktspalten. Die Suche laeuft
 * ueber die Spalte, die FB-031 anlegt, und liefert bis dahin sauber nichts,
 * statt zu brechen (gleiches Vorgehen wie LeadAnonymizer::PERSONAL_COLUMNS).
 * Die beiden Einzelvorgaenge exportLead() und eraseLead() arbeiten dagegen
 * schon heute.
 */
class LeadDataRequestService
{
    /**
     * Spalte, in der FB-031 die normalisierte E-Mail-Adresse eines Leads
     * ablegt. Bis dahin existiert sie nicht.
     */
    private const EMAIL_COLUMN = 'email_normalized';

    /**
     * Tabelle mit den Rohantworten. Legt FB-031 an.
     */
    private const ANSWERS_TABLE = 'lead_answers';

    public function __construct(
        private readonly LeadAnonymizer $leadAnonymizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Auskunft zu einer E-Mail-Adresse.
     *
     * @return array{email: string, generated_at: string, lead_count: int, leads: list<array<string, mixed>>}
     */
    public function exportForEmail(string $email, ?User $actor = null): array
    {
        $leads = $this->findLeadsByEmail($email);

        return [
            'email' => $this->normalizeEmail($email),
            'generated_at' => now()->toIso8601String(),
            'lead_count' => $leads->count(),
            'leads' => $leads
                ->map(fn (Lead $lead): array => $this->exportLead($lead, $actor))
                ->values()
                ->all(),
        ];
    }

    /**
     * Alles, was zu einem einzelnen Lead gespeichert ist.
     *
     * Ausgegeben werden die Rohspalten des Datensatzes -- nicht eine gepflegte
     * Feldliste. Eine Auskunft muss vollstaendig sein; eine Liste, die jemand
     * beim naechsten Feld zu erweitern vergisst, waere es nicht.
     *
     * Der Aufruf schreibt einen Audit-Eintrag: eine Auskunft ist eine
     * Offenlegung und muss nachvollziehbar sein.
     *
     * @return array<string, mixed>
     */
    public function exportLead(Lead $lead, ?User $actor = null): array
    {
        $this->auditLogger->log(
            AuditAction::DATA_EXPORTED,
            subject: $lead,
            payload: ['scope' => 'gdpr_access_request'],
            tenant: $lead->tenant,
            user: $actor,
        );

        return [
            'lead' => $lead->getAttributes(),
            'state_log' => $lead->stateLog
                ->map(fn (LeadStateLog $entry): array => $entry->getAttributes())
                ->values()
                ->all(),
            'answers' => $this->answersFor($lead),
        ];
    }

    /**
     * Loeschersuchen zu einer E-Mail-Adresse.
     *
     * @return int Anzahl der Leads, deren Personenbezug dieser Aufruf entfernt hat.
     */
    public function eraseForEmail(string $email, ?User $actor = null): int
    {
        $erased = 0;

        foreach ($this->findLeadsByEmail($email) as $lead) {
            if ($this->eraseLead($lead, $actor)) {
                $erased++;
            }
        }

        return $erased;
    }

    /**
     * Entfernt den Personenbezug eines einzelnen Leads auf Ersuchen hin.
     *
     * @return bool true, wenn dieser Aufruf den Lead anonymisiert hat; false,
     *              wenn er es bereits war.
     */
    public function eraseLead(Lead $lead, ?User $actor = null): bool
    {
        if (! $this->leadAnonymizer->anonymize($lead)) {
            return false;
        }

        $this->auditLogger->log(
            AuditAction::DATA_ERASED,
            subject: $lead,
            payload: ['scope' => 'gdpr_erasure_request'],
            tenant: $lead->tenant,
            user: $actor,
        );

        return true;
    }

    /**
     * Alle Leads zu einer E-Mail-Adresse.
     *
     * @return Collection<int, Lead>
     */
    public function findLeadsByEmail(string $email): Collection
    {
        if (! Schema::hasColumn('leads', self::EMAIL_COLUMN)) {
            // Bis FB-031 die Kontaktspalten anlegt, gibt es nichts zu finden.
            return new Collection;
        }

        return Lead::query()
            ->with('stateLog')
            ->where(self::EMAIL_COLUMN, $this->normalizeEmail($email))
            ->get();
    }

    /**
     * Rohantworten eines Leads, solange es sie gibt.
     *
     * @return list<array<string, mixed>>
     */
    private function answersFor(Lead $lead): array
    {
        if (! Schema::hasTable(self::ANSWERS_TABLE)) {
            return [];
        }

        return DB::table(self::ANSWERS_TABLE)
            ->where('lead_id', $lead->getKey())
            ->get()
            ->map(static fn (object $answer): array => (array) $answer)
            ->values()
            ->all();
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
