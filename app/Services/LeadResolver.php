<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\CallAttemptOutcome;
use App\Constants\LeadResolutionReason;
use App\Events\Lead\LeadResolved;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Entscheidet die Erreichbarkeit eines Leads (FB-083).
 *
 * Liest die bewerteten Versuche eines Leads und setzt daraus `contact_status`,
 * `resolved_at` und `resolved_by` -- an genau einer Stelle, weil daran die
 * Abrechnung haengt. Angesprochen wird der Dienst unmittelbar nach der
 * Bewertung im Dial-Rueckruf (FB-082, Ticket #8) und spaeter vom Scheduler,
 * wenn eine Frist ablaeuft (Ticket #11).
 *
 * `lead_state` fasst dieser Dienst nicht an: Das ist die andere Achse und
 * gehoert dem LeadStateService (Architekturleitsatz 1 und 2).
 *
 * Zwei Regeln fuehren zu einem Endstand:
 *
 *  1. ein angenommener Versuch -> billable (answered);
 *  2. mindestens config('lead_calls.unreachable_attempts') gueltige erfolglose
 *     Versuche an mindestens config('lead_calls.unreachable_min_days')
 *     verschiedenen Kalendertagen -> unreachable (three_attempts).
 *
 * Kalendertag heisst Europe/Berlin und nicht UTC: Die Anwendung laeuft auf UTC,
 * ein Versuch um 23:30 Ortszeit faellt dort auf den Folgetag und die Regel
 * "an zwei Tagen" waere um Mitternacht herum falsch erfuellt.
 *
 * Die Rechnungsposition bzw. die Gutschrift haengt sich an LeadResolved
 * (FB-085) -- hier wird nur entschieden und das Ereignis gefeuert.
 */
class LeadResolver
{
    /** Kalendertage werden in dieser Zone gezaehlt, nicht in UTC. */
    private const CALENDAR_TIMEZONE = 'Europe/Berlin';

    /**
     * Einstieg nach einem bewerteten Versuch.
     *
     * Der Kaeufer des Versuchs wandert als Ausloeser ins Ereignis -- bei einem
     * geteilten Lead (FB-055) ist das die Antwort auf die Frage, wessen Anruf
     * den Ausschlag gab.
     */
    public function resolveAfter(CallAttempt $attempt): void
    {
        $lead = $attempt->lead;

        if ($lead === null) {
            return;
        }

        $this->evaluate($lead, (int) $attempt->tenant_id);
    }

    /**
     * Wertet die Versuche eines Leads aus und schliesst ihn ggf. ab.
     *
     * Ohne Wirkung, solange die Erreichbarkeit schon entschieden ist oder der
     * Lead vor dem Cutover ausgeliefert wurde.
     */
    public function evaluate(Lead $lead, ?int $buyerId = null): void
    {
        if (! $this->isSubjectToRules($lead)) {
            return;
        }

        $reason = $this->reasonFor($lead);

        if ($reason === null) {
            return;
        }

        $this->resolve($lead, $reason, $buyerId);
    }

    /**
     * Schreibt den Endstand und feuert LeadResolved -- genau einmal.
     *
     * Die Entscheidung faellt unter `lockForUpdate`, weil zwei Rueckrufe von
     * Twilio gleichzeitig eintreffen koennen: Der zweite sieht den bereits
     * gesetzten Stand und steigt aus, statt ein zweites Ereignis zu feuern und
     * damit eine zweite Rechnungsposition auszuloesen.
     *
     * Der Scheduler benutzt diesen Weg fuer den Fristablauf (Ticket #11)
     * unmittelbar, ohne den Umweg ueber evaluate().
     *
     * @return bool Ob dieser Aufruf entschieden hat.
     */
    public function resolve(Lead $lead, LeadResolutionReason $reason, ?int $buyerId = null): bool
    {
        $status = $reason->contactStatus();

        $resolved = DB::transaction(function () use ($lead, $reason, $status): bool {
            /** @var Lead $locked */
            $locked = Lead::query()
                // Ohne Mandanten-Scope, aus demselben Grund wie im
                // LeadStateService: Der Lead gehoert dem Betreiber, entschieden
                // wird er aber im Kontext des Kaeufers oder ganz ohne Kontext
                // (Rueckruf, Konsole). Berechtigt hat der Aufrufer.
                ->withoutGlobalScopes(TenantScopes::names())
                ->whereKey($lead->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->contact_status->isResolved()) {
                return false;
            }

            $locked->forceFill([
                'contact_status' => $status,
                'resolved_at' => now(),
                'resolved_by' => $reason,
            ])->save();

            // Die vom Aufrufer gehaltene Instanz auf den geschriebenen Stand
            // bringen, damit sie nicht veraltet weiterverwendet wird.
            $lead->setRawAttributes($locked->getAttributes(), sync: true);

            return true;
        });

        if (! $resolved) {
            return false;
        }

        Log::info('Erreichbarkeit entschieden.', [
            'lead_id' => (int) $lead->getKey(),
            'buyer_id' => $buyerId,
            'contact_status' => $status->value,
            'resolved_by' => $reason->value,
        ]);

        event(new LeadResolved((int) $lead->getKey(), $buyerId, $status, $reason));

        return true;
    }

    /**
     * Unterliegt dieser Lead ueberhaupt dem Regelwerk?
     *
     * Der Cutover aus config('lead_calls.enforce_from') schuetzt Bestandsleads:
     * Wer vor der Einfuehrung ausgeliefert wurde, hatte nie die Gelegenheit,
     * ueber das Portal anzurufen -- ihn nachtraeglich als unerreichbar
     * abzuschliessen waere eine Gutschrift ohne Grundlage. Ohne
     * `delivered_at` laeuft gar keine Frist.
     */
    private function isSubjectToRules(Lead $lead): bool
    {
        if ($lead->contact_status->isResolved()) {
            return false;
        }

        $enforceFrom = config('lead_calls.enforce_from');

        if ($enforceFrom === null || $enforceFrom === '') {
            return true;
        }

        return $lead->delivered_at !== null
            && $lead->delivered_at->greaterThanOrEqualTo(Carbon::parse((string) $enforceFrom));
    }

    /**
     * Die Begruendung, die aus den Versuchen folgt -- oder null, solange der
     * Lead offen bleibt.
     */
    private function reasonFor(Lead $lead): ?LeadResolutionReason
    {
        if ($this->hasAnsweredAttempt($lead)) {
            return LeadResolutionReason::ANSWERED;
        }

        if ($this->attemptsExhausted($lead)) {
            return LeadResolutionReason::THREE_ATTEMPTS;
        }

        return null;
    }

    private function hasAnsweredAttempt(Lead $lead): bool
    {
        return $this->attempts($lead)
            ->where('outcome', CallAttemptOutcome::ANSWERED)
            ->exists();
    }

    /**
     * Sind Zahl und Streuung der gueltigen Fehlversuche erreicht?
     *
     * Beide Bedingungen gelten zusammen: drei Versuche an einem Nachmittag
     * sind keine Erreichbarkeitspruefung.
     */
    private function attemptsExhausted(Lead $lead): bool
    {
        $required = (int) config('lead_calls.unreachable_attempts');
        $requiredDays = (int) config('lead_calls.unreachable_min_days');

        /** @var Collection<int, CallAttempt> $failed */
        $failed = $this->attempts($lead)
            ->where('outcome', CallAttemptOutcome::FAILED_VALID)
            ->get();

        if ($failed->count() < $required) {
            return false;
        }

        return $this->distinctCalendarDays($failed) >= $requiredDays;
    }

    /**
     * Zahl der verschiedenen Kalendertage (Europe/Berlin), an denen versucht
     * wurde. Gezaehlt wird `started_at` -- der Zeitpunkt des Waehlens, nicht
     * der des Aufgebens.
     *
     * @param  Collection<int, CallAttempt>  $attempts
     */
    private function distinctCalendarDays(Collection $attempts): int
    {
        return $attempts
            ->filter(fn (CallAttempt $attempt): bool => $attempt->started_at instanceof Carbon)
            ->map(fn (CallAttempt $attempt): string => $attempt->started_at
                ->copy()
                ->setTimezone(self::CALENDAR_TIMEZONE)
                ->toDateString())
            ->unique()
            ->count();
    }

    /**
     * Alle Versuche des Leads, ueber Kaeufer hinweg.
     *
     * Bei einem geteilten Lead (FB-055) zaehlt die Frist dem Lead als Ganzem;
     * der Mandantenfilter bleibt deshalb draussen -- sonst saehe jeder Kaeufer
     * nur seine eigenen Versuche und die Regel griffe nie.
     *
     * @return Builder<CallAttempt>
     */
    private function attempts(Lead $lead): Builder
    {
        return CallAttempt::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('lead_id', $lead->getKey());
    }
}
