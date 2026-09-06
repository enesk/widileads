<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

/**
 * Was an einer Einreichung auffaellig ist (FB-023).
 *
 * Bewusst eine Sammlung von Beobachtungen, kein Urteil: Ob daraus ein
 * ungueltiger Lead wird, entscheidet der Pruefjob in FB-033. Hier wird nur
 * festgehalten, was gemessen wurde -- Beweis vor Bewertung.
 *
 * Einzige Ausnahme ist das Rate-Limit: Es schuetzt die Anwendung selbst und
 * muss deshalb sofort greifen, sonst waere es wirkungslos.
 */
class SpamAssessment
{
    public function __construct(
        public readonly bool $honeypotTripped = false,
        public readonly bool $submittedTooFast = false,
        public readonly bool $rateLimited = false,
        public readonly ?int $duplicateOfLeadId = null,
        public readonly ?int $secondsOnFunnel = null,
    ) {}

    /**
     * Nur das Rate-Limit haelt eine Einreichung auf. Honeypot und Zeitfalle
     * werden still vermerkt: Wer einem Bot sagt, woran er erkannt wurde, bekommt
     * beim naechsten Mal einen besseren Bot.
     */
    public function blocksSubmission(): bool
    {
        return $this->rateLimited;
    }

    public function isSuspicious(): bool
    {
        return $this->honeypotTripped || $this->submittedTooFast || $this->rateLimited;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'honeypot_tripped' => $this->honeypotTripped,
            'submitted_too_fast' => $this->submittedTooFast,
            'rate_limited' => $this->rateLimited,
            'duplicate_of_lead_id' => $this->duplicateOfLeadId,
            'seconds_on_funnel' => $this->secondsOnFunnel,
        ];
    }
}
