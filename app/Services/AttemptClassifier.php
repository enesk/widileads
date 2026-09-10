<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\CallAttemptOutcome;
use App\Models\CallAttempt;
use App\Models\Scopes\TenantScopes;
use Illuminate\Support\Carbon;

/**
 * Bewertung eines abgeschlossenen Anrufversuchs (FB-083).
 *
 * Die einzige Stelle, an der `outcome` und `ignore_reason` berechnet werden.
 * Angesprochen wird sie vom Dial-Rueckruf, sobald das Ergebnis des Lead-Beins
 * vorliegt (FB-082, Ticket #8).
 *
 * Kein Wert von Twilio wird als Bewertung uebernommen: `dial_status`,
 * `answered_by` und `duration_seconds` sind Belege, das Ergebnis leiten wir
 * daraus selbst ab. Saemtliche Schwellwerte stehen in config('lead_calls.*').
 *
 * Ein bereits bewerteter Versuch wird nie neu bewertet -- daran haengen
 * Abrechnung und Gutschrift, und der Rueckruf von Twilio kommt wiederholt.
 */
class AttemptClassifier
{
    /** Grund, mit dem ein Versuch unter dem Mindestabstand verworfen wird. */
    public const IGNORE_TOO_SOON = 'too_soon';

    /** Grund, mit dem ein Versuch an einem entschiedenen Lead verworfen wird. */
    public const IGNORE_LEAD_CLOSED = 'lead_closed';

    public function classify(CallAttempt $attempt): CallAttempt
    {
        if ($attempt->outcome !== null) {
            return $attempt;
        }

        // Ist die Erreichbarkeit bereits entschieden, aendert kein weiterer
        // Anruf etwas daran -- auch kein angenommener. Deshalb steht diese
        // Pruefung vor allen anderen.
        if (! $this->leadIsOpen($attempt)) {
            return $this->ignore($attempt, self::IGNORE_LEAD_CLOSED);
        }

        if ($this->wasAnswered($attempt)) {
            return $this->apply($attempt, CallAttemptOutcome::ANSWERED, null);
        }

        if ($this->isTooSoon($attempt)) {
            return $this->ignore($attempt, self::IGNORE_TOO_SOON);
        }

        return $this->apply($attempt, CallAttemptOutcome::FAILED_VALID, null);
    }

    /**
     * Der Lead ist erreicht, wenn das Gespraech zustande kam, ein Mensch
     * abgenommen hat und es lang genug war.
     *
     * Fehlt die Dauer, zaehlt sie als null: Ein Beleg, den wir nicht haben,
     * darf nicht als Erfolg durchgehen.
     */
    private function wasAnswered(CallAttempt $attempt): bool
    {
        if ($attempt->dial_status !== 'completed') {
            return false;
        }

        if ($attempt->answered_by !== null && str_starts_with($attempt->answered_by, 'machine')) {
            return false;
        }

        return (int) ($attempt->duration_seconds ?? 0) >= (int) config('lead_calls.answered_min_seconds');
    }

    /**
     * Liegt der letzte gueltige Fehlversuch desselben Leads noch keine
     * `retry_min_hours` zurueck?
     *
     * Gemessen wird von Beginn zu Beginn -- die Gespraechsdauer des
     * Vorgaengers darf den Abstand nicht verschieben. Verworfene Versuche
     * halten die Uhr nicht an, sie zaehlen hier nicht mit.
     */
    private function isTooSoon(CallAttempt $attempt): bool
    {
        $hours = (int) config('lead_calls.retry_min_hours');

        if ($hours <= 0 || ! $attempt->started_at instanceof Carbon) {
            return false;
        }

        // Gezaehlt wird ueber den Lead, nicht ueber den Kaeufer: Bei einem
        // geteilten Lead (FB-055) klingelt sonst dasselbe Telefon zweimal
        // kurz hintereinander. Der Mandantenfilter bleibt deshalb draussen.
        /** @var CallAttempt|null $previous */
        $previous = CallAttempt::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('lead_id', $attempt->lead_id)
            ->whereKeyNot($attempt->getKey())
            ->where('outcome', CallAttemptOutcome::FAILED_VALID)
            ->where('started_at', '<=', $attempt->started_at)
            ->latest('started_at')
            ->first();

        if ($previous === null || ! $previous->started_at instanceof Carbon) {
            return false;
        }

        return $previous->started_at->diffInHours($attempt->started_at, true) < $hours;
    }

    private function leadIsOpen(CallAttempt $attempt): bool
    {
        $lead = $attempt->lead;

        return $lead !== null && $lead->isOpen();
    }

    private function ignore(CallAttempt $attempt, string $reason): CallAttempt
    {
        return $this->apply($attempt, CallAttemptOutcome::FAILED_IGNORED, $reason);
    }

    private function apply(CallAttempt $attempt, CallAttemptOutcome $outcome, ?string $reason): CallAttempt
    {
        $attempt->forceFill([
            'outcome' => $outcome,
            'ignore_reason' => $reason,
        ])->save();

        return $attempt;
    }
}
