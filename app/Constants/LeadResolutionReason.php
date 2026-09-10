<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Warum die Erreichbarkeit eines Leads entschieden wurde (FB-082).
 *
 * Steht in `leads.resolved_by` und ist die Begruendung gegenueber dem Kaeufer:
 * Aus ihr geht hervor, ob ein Anruf angenommen wurde, die Versuche
 * ausgeschoepft waren oder schlicht die Frist ablief. Null heisst: noch offen.
 */
enum LeadResolutionReason: string
{
    /** Ein Versuch war lang genug (config('lead_calls.answered_min_seconds')). */
    case ANSWERED = 'answered';

    /**
     * Genug gueltige erfolglose Versuche ueber genug Tage
     * (config('lead_calls.unreachable_attempts') und `unreachable_min_days`).
     * Der Wert heisst historisch `three_attempts`, die Zahl selbst steht in
     * der Konfiguration.
     */
    case THREE_ATTEMPTS = 'three_attempts';

    /**
     * Die Frist (config('lead_calls.deadline_days')) lief ab, ohne dass die
     * Versuche ausgeschoepft waren. Der Kaeufer hatte Gelegenheit genug --
     * der Lead wird abgerechnet, nicht gutgeschrieben (Ticket #11). Dieselbe
     * Linie faehrt FB-058 bei der Reklamationsfrist.
     */
    case DEADLINE = 'deadline';

    /**
     * Der Stand, in den diese Begruendung fuehrt.
     */
    public function contactStatus(): LeadContactStatus
    {
        return match ($this) {
            self::ANSWERED, self::DEADLINE => LeadContactStatus::BILLABLE,
            self::THREE_ATTEMPTS => LeadContactStatus::UNREACHABLE,
        };
    }

    public function label(): string
    {
        return __('call.resolution.'.$this->value);
    }
}
