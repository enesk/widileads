<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\LeadContactStatus;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Constants\LeadTransitions;
use App\Events\Lead\LeadStateChanged;
use App\Exceptions\IllegalLeadTransition;
use App\Models\Lead;
use App\Models\LeadStateLog;
use App\Models\Scopes\TenantScopes;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Die einzige Stelle, an der ein Lead seinen Zustand wechselt (FB-030).
 *
 * Architekturleitsatz 2: Jeder Zustandswechsel geht durch transition(). Ein
 * direktes update(['lead_state' => ...]) irgendwo sonst ist ein Bug -- der
 * Architektur-Test aus FB-042 schlaegt darauf an.
 *
 * Der Dienst haelt drei Zusagen ein:
 *
 * 1. Erlaubt ist nur, was in LeadTransitions::TABLE steht.
 * 2. Zustand und Protokolleintrag entstehen in EINER Transaktion. Es gibt
 *    keinen Zustandswechsel ohne Beleg und keinen Beleg ohne Wechsel.
 * 3. Beim Eintritt in einen Endzustand wird der Preis einmalig festgeschrieben
 *    und danach nie geaendert (Architekturleitsatz 4: Geld folgt Belegen).
 */
class LeadStateService
{
    /**
     * Wechselt den Zustand eines Leads.
     *
     * Der Lead wird innerhalb der Transaktion erneut gelesen und gesperrt
     * (SELECT ... FOR UPDATE). Greifen zwei Vorgaenge gleichzeitig auf denselben
     * Lead zu -- etwa zwei Kaeufer auf denselben Marktplatzeintrag (FB-054) --,
     * gewinnt genau einer; der andere erhaelt eine IllegalLeadTransition.
     *
     * @param  Lead  $lead  Der Lead; die uebergebene Instanz wird auf den neuen Stand gebracht.
     * @param  LeadState  $to  Zielzustand.
     * @param  LeadTransitionReason  $reason  Warum gewechselt wird -- wird unveraenderlich protokolliert.
     * @param  User|null  $actor  Ausloesender Benutzer. Bewusst ohne Rueckgriff auf den
     *                            angemeldeten Benutzer: automatische Uebergaenge aus Jobs und
     *                            Scheduler sollen als solche erkennbar bleiben (null).
     * @param  array<string, mixed>  $meta  Zusatzangaben zum Vorgang, z. B. Kaeufer oder Begruendung.
     *
     * @throws IllegalLeadTransition wenn der Uebergang nicht in LeadTransitions::TABLE steht
     *                               oder der Zustand zwischenzeitlich gewechselt hat.
     */
    public function transition(
        Lead $lead,
        LeadState $to,
        LeadTransitionReason $reason,
        ?User $actor = null,
        array $meta = [],
    ): Lead {
        $from = $lead->lead_state;

        // Frueher Abbruch ohne Datenbankzugriff. Der eigentliche Schutz sitzt
        // unten unter der Sperre, weil sich der Zustand bis dahin geaendert
        // haben kann.
        if (! LeadTransitions::isAllowed($from, $to)) {
            throw IllegalLeadTransition::between($from, $to);
        }

        return DB::transaction(function () use ($lead, $from, $to, $reason, $actor, $meta): Lead {
            $locked = Lead::query()
                // Ohne Mandanten-Scope, und zwar an genau dieser einen Abfrage
                // -- nicht am Modell und nicht global (FB-055a).
                //
                // Diese Zeile ist KEINE Erlaubnis, den Scope anderswo zu
                // umgehen. Sie holt keinen Lead, sie sperrt einen, den der
                // Aufrufer bereits identifiziert hat. Fuer die Berechtigung ist
                // ausschliesslich der Aufrufer zustaendig: MarketplaceListing,
                // PurchaseLead, LeadResource -- also die Stellen, die einen Lead
                // HOLEN. Dieser Dienst ist eine interne Zustandsmaschine, keine
                // Berechtigungsgrenze.
                //
                // Mit Scope faende die Abfrage im Kaeufer-Kontext nichts, weil
                // er dort auf die tenant_id des Kaeufers zielt, der Lead aber
                // dem Betreiber gehoert. firstOrFail() wuerde werfen, und jeder
                // Kauf durch einen eingeloggten Kaeufer schluege fehl -- nicht
                // nur der Mehrfachverkauf, sondern FB-054 vollstaendig. Ein
                // Scope an der Sperre schuetzt nichts; er verhindert nur den
                // legitimen Zugriff.
                ->withoutGlobalScopes(TenantScopes::names())
                ->whereKey($lead->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->lead_state !== $from) {
                throw IllegalLeadTransition::raced($from, $locked->lead_state, $to);
            }

            $locked->lead_state = $to;

            if ($to->isFinal() && ! $locked->isSettled()) {
                $locked->settled_price = $this->settlementPriceFor($locked);
                $locked->settled_at = now();
            }

            $locked->save();

            $logEntry = LeadStateLog::query()->create([
                'lead_id' => $locked->getKey(),
                'from_state' => $from,
                'to_state' => $to,
                'reason' => $reason,
                'actor_id' => $actor?->getKey(),
                'meta' => $meta === [] ? null : $meta,
            ]);

            // Die vom Aufrufer gehaltene Instanz auf den geschriebenen Stand
            // bringen, damit sie nicht veraltet weiterverwendet wird.
            $lead->setRawAttributes($locked->getAttributes(), sync: true);

            event(new LeadStateChanged($lead, $from, $to, $reason, $actor, $logEntry));

            return $lead;
        });
    }

    /**
     * Waere dieser Uebergang erlaubt? Fuer Oberflaechen, die nur moegliche
     * Aktionen anbieten sollen (FB-036), statt die Ausnahme abzufangen.
     */
    public function canTransition(Lead $lead, LeadState $to): bool
    {
        return LeadTransitions::isAllowed($lead->lead_state, $to);
    }

    /**
     * Alle Zustaende, die von diesem Lead aus erreichbar sind.
     *
     * @return list<LeadState>
     */
    public function allowedTargetsFor(Lead $lead): array
    {
        return LeadTransitions::allowedFrom($lead->lead_state);
    }

    /**
     * Setzt verkaufte Leads auf "verfuegbar" zurueck -- ohne Uebergang.
     *
     * Der Weg fuer `wallet:reset` (Ticket #42): Ein Reset ist kein fachlicher
     * Uebergang. Er soll kein Ereignis feuern, keine Buchung ausloesen und
     * nicht im Zustandsprotokoll stehen -- sonst raeumte der Lauf auf und
     * buchte im selben Atemzug neu. Trotzdem gehoert das Schreiben hierher:
     * `lead_state`, `settled_price` und `settled_at` haben genau einen
     * Eigentuemer (Architekturleitsatz 2 und 4), und ein zweiter Schreibweg
     * im Kommando waere der Anfang einer Ausnahmeliste.
     *
     * Unangetastet bleiben Leads in `neu`, `ungueltig` und `abgelaufen` sowie
     * anonymisierte: Sie waren nie verkaeuflich oder duerfen es nicht mehr
     * werden. Die Felder aus Auslieferung und Erreichbarkeitspruefung werden
     * mit geleert -- die Belege dazu haengen an den Kaufbelegen, und die sind
     * zu diesem Zeitpunkt weg.
     *
     * @return int Zahl der zurueckgesetzten Leads
     */
    public function resetSoldLeadsToAvailable(): int
    {
        return Lead::query()
            ->withoutGlobalScopes()
            ->whereNull('anonymized_at')
            ->whereIn('lead_state', [
                LeadState::RESERVIERT->value,
                LeadState::VERKAUFT->value,
                LeadState::ERREICHT->value,
                LeadState::UNERREICHBAR->value,
            ])
            ->update([
                'lead_state' => LeadState::VERFUEGBAR->value,
                'reserved_by' => null,
                'reserved_until' => null,
                'settled_price' => null,
                'settled_at' => null,
                'delivered_at' => null,
                'deadline_at' => null,
                // Die Erreichbarkeit haengt an den Anrufversuchen, und die sind
                // mit den Kaufbelegen weg. `open` ist ihr Ausgangswert, die
                // Spalte ist nicht nullbar.
                'contact_status' => LeadContactStatus::OPEN->value,
                'resolved_at' => null,
                'resolved_by' => null,
                'phone_revealed_at' => null,
                'reminder_sent_at' => null,
            ]);
    }

    /**
     * Preis, der beim Eintritt in einen Endzustand festgeschrieben wird.
     *
     * FB-031 ergaenzt die Spalte `price_at_creation`, in der der beim Anlegen
     * gueltige Funnel-Preis steht. Solange es sie nicht gibt, gilt der
     * konfigurierte Standardpreis. Der Wert wird hier gelesen und nie
     * hartkodiert (Architekturleitsatz 8).
     */
    private function settlementPriceFor(Lead $lead): float
    {
        $priceAtCreation = $lead->getAttribute('price_at_creation');

        if (is_numeric($priceAtCreation)) {
            return (float) $priceAtCreation;
        }

        return (float) config('funnel.lead.default_price');
    }
}
