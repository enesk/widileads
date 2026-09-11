<?php

declare(strict_types=1);

namespace App\Listeners\Lead;

use App\Constants\LeadContactStatus;
use App\Constants\PurchaseStatus;
use App\Events\Lead\LeadResolved;
use App\Mail\Wallet\LeadSettlementFailed;
use App\Models\LeadPurchase;
use App\Services\SupportMailbox;
use App\Services\Wallet\PurchaseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Die Erreichbarkeitsentscheidung schliesst die Geldseite ab (LP-WALLET-008).
 *
 * Dies ist im laufenden Betrieb der einzige Ort, an dem abgebucht oder
 * aufgeloest wird: `billable` heisst abrechnen, `unreachable` heisst
 * Reservierung freigeben. Eine Erstattung dagegen ist eine Admin-Entscheidung
 * und laeuft nie ueber dieses Ereignis.
 *
 * **Alle reservierten Kaeufe des Leads, nicht nur der ausloesende Kaeufer.**
 * `contact_status` haengt am Lead, nicht am Kaufbeleg -- im Modus `shared`
 * (FB-055) gehoert derselbe Lead mehreren Kaeufern, und eine einmal getroffene
 * Entscheidung gilt fuer sie alle. `buyerId` des Ereignisses sagt nur, wessen
 * Anruf entschieden hat, und taugt deshalb nicht als Filter; bei Fristablauf
 * ist er ohnehin null.
 *
 * **In der Queue, mit drei Versuchen.** Abbuchen heisst vier Ledger-Zeilen
 * schreiben; das gehoert nicht in den HTTP-Request eines Twilio-Rueckrufs. Die
 * Wiederholung ist gefahrlos, weil der PurchaseService jeden Vorgang
 * idempotent haelt: Ein Kauf im Zielzustand kommt unveraendert zurueck, jede
 * Buchung traegt ihren Idempotenzschluessel.
 */
class SettleLeadPurchase implements ShouldQueue
{
    /**
     * @var int Drei Versuche: Ein Deadlock auf der Kaufzeile oder eine kurz
     *          nicht erreichbare Datenbank soll sich von selbst erledigen.
     */
    public int $tries = 3;

    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly SupportMailbox $support,
    ) {}

    public function handle(LeadResolved $event): void
    {
        if (! $event->contactStatus->isResolved()) {
            Log::warning('Leadkauf nicht abgerechnet: Erreichbarkeit ist gar nicht entschieden.', [
                'lead_id' => $event->leadId,
                'contact_status' => $event->contactStatus->value,
            ]);

            return;
        }

        $target = $event->contactStatus === LeadContactStatus::BILLABLE
            ? PurchaseStatus::CAPTURED
            : PurchaseStatus::RELEASED;

        // Auch die schon im Zielzustand stehenden Kaeufe werden geladen: Bei
        // einer zweiten Zustellung soll der Vorgang durchlaufen und nichts tun,
        // statt als "kein Kaufbeleg" im Protokoll zu landen.
        $purchases = LeadPurchase::query()
            ->where('lead_id', $event->leadId)
            ->whereIn('status', [PurchaseStatus::RESERVED->value, $target->value])
            ->get();

        if ($purchases->isEmpty()) {
            // Altbestand vor dem Cutover: ausgeliefert, ohne dass je Geld
            // reserviert wurde. Auch ein schon erstatteter Kauf landet hier --
            // in beiden Faellen gibt es nichts abzurechnen.
            Log::info('Leadkauf uebersprungen: kein reservierter Kaufbeleg zum Lead.', [
                'lead_id' => $event->leadId,
                'contact_status' => $event->contactStatus->value,
            ]);

            return;
        }

        foreach ($purchases as $purchase) {
            $settled = $target === PurchaseStatus::CAPTURED
                ? $this->purchases->capture($purchase)
                : $this->purchases->release($purchase);

            Log::info('Geldseite des Leadkaufs abgeschlossen.', [
                'lead_id' => $event->leadId,
                'lead_purchase_id' => (int) $settled->getKey(),
                'buyer_tenant_id' => (int) $settled->buyer_tenant_id,
                'status' => $settled->status->value,
            ]);
        }
    }

    /**
     * Nach dem letzten Versuch: Der Lead ist entschieden, das Geld haengt.
     *
     * Das muss ein Mensch sehen -- der Kaeufer haette sonst eine Reservierung,
     * die niemand mehr aufloest. Der Fehler steht im Protokoll, die Meldung
     * geht an die Support-Adresse; eine fehlende Adresse darf hier nicht noch
     * eine zweite Ausnahme erzeugen.
     */
    public function failed(LeadResolved $event, Throwable $exception): void
    {
        Log::error('Leadkauf konnte nicht abgerechnet werden.', [
            'lead_id' => $event->leadId,
            'buyer_id' => $event->buyerId,
            'contact_status' => $event->contactStatus->value,
            'resolved_by' => $event->resolvedBy->value,
            'exception' => $exception->getMessage(),
        ]);

        $recipient = $this->support->addressOrLog(
            'Fehlgeschlagene Abrechnung ohne Support-Meldung: app.support_email ist nicht brauchbar gesetzt.',
            ['lead_id' => $event->leadId],
        );

        if ($recipient === null) {
            return;
        }

        Mail::to($recipient)->send(new LeadSettlementFailed(
            leadId: $event->leadId,
            contactStatus: $event->contactStatus,
            reason: $exception->getMessage(),
        ));
    }
}
