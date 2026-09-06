<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Constants\LeadTransitions;
use App\Events\Lead\LeadStateChanged;
use App\Exceptions\IllegalLeadTransition;
use App\Models\Lead;
use App\Models\LeadStateLog;
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
