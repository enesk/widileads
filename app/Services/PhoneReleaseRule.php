<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\CallAttemptOutcome;
use App\Constants\LeadContactStatus;
use App\Constants\PhoneReleaseReason;
use App\Dto\PhoneRelease;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Die einzige Stelle, die entscheidet, wann ein Kaeufer die Rufnummer eines
 * gekauften Leads im Klartext bekommt (Ticket #5).
 *
 * Geprueft wird in dieser Reihenfolge, und die Reihenfolge ist die Regel:
 *
 *  1. **Gutschrift schlaegt Freigabe.** Ist der Lead als nicht erreichbar
 *     geschlossen (`contact_status = unreachable`), bleibt die Nummer
 *     verdeckt -- auch dann, wenn sie ueber Weg 3 schon sichtbar war. Entweder
 *     Geld oder Nummer, nicht beides.
 *  2. Der Lead wird abgerechnet (`contact_status = billable`). Das ist der
 *     Weg aus FB-085 und bleibt: Wer bezahlt, bekommt die Nummer -- auch ohne
 *     einen einzigen Anruf, etwa wenn die Frist ablaeuft.
 *  3. Ein Anruf dieses Kaeufers wurde angenommen (`outcome = answered`). Die
 *     Gespraechsdauer prueft dieser Dienst nicht nach: Ob ein Gespraech lang
 *     genug war, hat der AttemptClassifier mit
 *     config('lead_calls.answered_min_seconds') schon entschieden und im
 *     Ergebnis festgeschrieben. Eine zweite Rechnung waere eine zweite Regel.
 *  4. Dieser Kaeufer hat config('lead_calls.unreachable_attempts') gezaehlte
 *     Versuche hinter sich. Gezaehlt heisst CallAttemptOutcome::counts() --
 *     also `answered` und `failed_valid`. Ein Versuch in der Sperrzeit oder zu
 *     frueh zaehlt nicht; er kostet den Kaeufer nichts und bringt ihm nichts.
 *
 * **Weg 4 ist eine Freigabe auf Zeit.** Sie gilt, solange die Erreichbarkeit
 * offen ist. Zwischen dem dritten Versuch und der Gutschrift liegt Zeit --
 * `unreachable` braucht zusaetzlich eine Streckung ueber
 * config('lead_calls.unreachable_min_days') und wird vom Scheduler
 * entschieden. In diesem Fenster hat der Kaeufer die Nummer gesehen; wir
 * nehmen sie danach aus der Anzeige, aus dem Export und aus der API, aber
 * nicht aus seinem Notizbuch. Dass sie heraus war, steht in
 * `leads.phone_revealed_at`. Dieser Zeitstempel ist Protokoll und haelt die
 * Anzeige ausdruecklich nicht offen.
 *
 * **Die Entscheidung gilt je Kaeufer, nicht je Lead.** Versuche haengen am
 * Kaufbeleg; bei einem geteilten Lead (FB-055) oeffnet der Fleiss des einen
 * Kaeufers dem anderen nichts. Nur Weg 1 und 2 sind leadweit -- sie haengen an
 * der Abrechnung, und die gilt fuer den Lead.
 *
 * Der Zaehler kommt aus `call_attempts` und nicht aus einer eigenen Spalte: Die
 * Versuche sind die Wahrheit, eine mitgefuehrte Zahl waere eine zweite.
 */
class PhoneReleaseRule
{
    /**
     * Der Freigabestand eines Kaeufers fuer einen Lead.
     */
    public function for(Lead $lead, Tenant $buyer): PhoneRelease
    {
        return $this->decide($lead, $this->outcomesOf($lead, $buyer));
    }

    /**
     * Derselbe Stand, ausgehend vom Kaufbeleg.
     *
     * Fuer Listen gedacht: Sind die Versuche schon geladen, zaehlt diese
     * Methode in der Sammlung statt je Zeile neu abzufragen. Entschieden wird
     * trotzdem in decide() -- es bleibt eine Regel, nur die Herkunft der
     * Versuche unterscheidet sich.
     */
    public function forPurchase(LeadPurchase $purchase): PhoneRelease
    {
        if (! $purchase->relationLoaded('callAttempts')) {
            return $this->decide($purchase->lead, $this->outcomesOfPurchase($purchase));
        }

        $outcomes = $purchase->callAttempts
            ->map(static fn (CallAttempt $attempt): ?CallAttemptOutcome => $attempt->outcome)
            ->filter()
            ->values()
            ->all();

        return $this->decide($purchase->lead, $outcomes);
    }

    /**
     * So viele gezaehlte Versuche geben die Nummer frei.
     */
    public function requiredAttempts(): int
    {
        return (int) config('lead_calls.unreachable_attempts');
    }

    /**
     * @param  list<CallAttemptOutcome>  $outcomes
     */
    private function decide(Lead $lead, array $outcomes): PhoneRelease
    {
        $counted = count(array_filter(
            $outcomes,
            static fn (CallAttemptOutcome $outcome): bool => $outcome->counts(),
        ));

        $required = $this->requiredAttempts();

        $stand = function (bool $released, PhoneReleaseReason $reason) use ($counted, $required): PhoneRelease {
            return new PhoneRelease($released, $counted, $required, $reason);
        };

        // Die Gutschrift steht vor allem anderen und nimmt eine Freigabe nach
        // Versuchen ausdruecklich zurueck: entweder Geld oder Nummer, nicht
        // beides (Ticket #5, Nachtrag vom 11.10.2026).
        if ($lead->contact_status === LeadContactStatus::UNREACHABLE) {
            return $stand(false, PhoneReleaseReason::REVOKED_CREDIT);
        }

        if ($lead->contact_status === LeadContactStatus::BILLABLE) {
            return $stand(true, PhoneReleaseReason::BILLABLE);
        }

        if (in_array(CallAttemptOutcome::ANSWERED, $outcomes, true)) {
            return $stand(true, PhoneReleaseReason::ANSWERED);
        }

        // Freigabe auf Zeit: Sie gilt, solange die Erreichbarkeit offen ist.
        if ($counted >= $required) {
            return $stand(true, PhoneReleaseReason::ATTEMPTS);
        }

        return $stand(false, PhoneReleaseReason::LOCKED);
    }

    /**
     * Die Bewertungen der Versuche eines Kaeufers bei einem Lead -- ohne
     * Mandanten-Scope.
     *
     * Gefragt wird im Kontext des Kaeufers, aber auch aus dem Betreiber-Panel
     * und ueber die API, wo der aktive Mandant ein anderer ist. Eingeschraenkt
     * wird deshalb ausdruecklich auf `call_attempts.tenant_id` des Kaeufers,
     * statt sich auf einen Scope zu verlassen, der auf den falschen Mandanten
     * zielen kann (FB-092).
     *
     * @return list<CallAttemptOutcome>
     */
    private function outcomesOf(Lead $lead, Tenant $buyer): array
    {
        return $this->outcomes(
            CallAttempt::query()
                ->withoutGlobalScopes(TenantScopes::names())
                ->where('lead_id', $lead->getKey())
                ->where('tenant_id', $buyer->getKey())
        );
    }

    /**
     * @return list<CallAttemptOutcome>
     */
    private function outcomesOfPurchase(LeadPurchase $purchase): array
    {
        return $this->outcomes(
            CallAttempt::query()
                ->withoutGlobalScopes(TenantScopes::names())
                ->where('lead_purchase_id', $purchase->getKey())
        );
    }

    /**
     * @param  Builder<CallAttempt>  $query
     * @return list<CallAttemptOutcome>
     */
    private function outcomes($query): array
    {
        return $query->whereNotNull('outcome')
            ->pluck('outcome')
            ->map(static fn (CallAttemptOutcome|string $outcome): CallAttemptOutcome => $outcome instanceof CallAttemptOutcome
                ? $outcome
                : CallAttemptOutcome::from($outcome))
            ->values()
            ->all();
    }
}
