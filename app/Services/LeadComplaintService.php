<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\PurchaseLead;
use App\Constants\ComplaintStatus;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Exceptions\ComplaintNotAllowedException;
use App\Mail\Lead\LeadComplaintFiled;
use App\Models\LeadComplaint;
use App\Models\LeadPurchase;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Reklamationen: beantragen, entscheiden, abrechnen (FB-058).
 *
 * Bis der Anrufnachweis kommt (FB-E7, nicht im Lieferumfang -- Entscheidung 6
 * vom 2026-09-06) ist die Reklamation der **einzige** Weg von `verkauft` nach
 * `erreicht` oder `unerreichbar`. Sie ist deshalb kein Provisorium, sondern
 * traegt die gesamte Abrechnung.
 *
 * Entschieden wird von Hand. Eine Gutschrift, die sich selbst bewilligt, waere
 * eine Einladung -- und der Kaeufer, der reklamiert, ist derselbe, der davon
 * profitiert.
 */
class LeadComplaintService
{
    public function __construct(
        private readonly LeadStateService $states,
        private readonly CreditLedgerService $credits,
    ) {}

    /**
     * Nimmt eine Reklamation entgegen.
     *
     * @throws ComplaintNotAllowedException wenn der Kauf nicht (mehr) reklamierbar ist
     */
    public function file(LeadPurchase $purchase, Tenant $buyer, LeadState $requestedState, string $reason): LeadComplaint
    {
        $this->guard($purchase, $buyer, $requestedState, $reason);

        $complaint = new LeadComplaint([
            'lead_purchase_id' => $purchase->getKey(),
            'lead_id' => $purchase->lead_id,
            'buyer_tenant_id' => $buyer->getKey(),
            'requested_state' => $requestedState,
            'reason' => trim($reason),
        ]);

        $complaint->status = ComplaintStatus::PENDING;
        $complaint->save();

        $this->notifySupport($complaint);

        return $complaint;
    }

    /**
     * Meldet den Antrag an die Support-Adresse.
     *
     * Ueber eine Reklamation entscheidet ein Mensch -- ohne diese Meldung
     * bliebe sie liegen, bis jemand von sich aus in die Pruefliste schaut.
     *
     * Ein fehlender oder unbrauchbarer Eintrag in `app.support_email` darf den
     * Antrag nicht scheitern lassen: Der Kaeufer hat seinen Teil getan, die
     * Reklamation steht in der Datenbank.
     */
    private function notifySupport(LeadComplaint $complaint): void
    {
        $recipient = (string) config('app.support_email');

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            Log::warning('Reklamation ohne Support-Meldung: app.support_email ist nicht gesetzt.', [
                'lead_complaint_id' => $complaint->getKey(),
            ]);

            return;
        }

        Mail::to($recipient)->send(new LeadComplaintFiled($complaint->loadMissing([
            'buyer',
            'purchase',
            // Ohne Mandanten-Scope: Lead und Fragebogen gehoeren dem Betreiber,
            // reklamiert wird im Kontext des Kaeufers.
            'lead' => static fn (Relation $lead) => $lead->withoutGlobalScopes(TenantScopes::names())
                ->with(['funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScopes(TenantScopes::names())]),
        ])));
    }

    /**
     * Erkennt eine Reklamation an: Der Lead wechselt in den beantragten
     * Zustand, der Kaeufer bekommt sein Guthaben zurueck.
     *
     * Beides in einer Transaktion -- ein Zustandswechsel ohne Gutschrift waere
     * eine stille Enteignung, eine Gutschrift ohne Wechsel ein verschenkter
     * Lead.
     */
    public function approve(LeadComplaint $complaint, User $reviewer, ?string $note = null): LeadComplaint
    {
        if (! $complaint->status->isPending()) {
            throw ComplaintNotAllowedException::alreadyDecided();
        }

        return DB::transaction(function () use ($complaint, $reviewer, $note): LeadComplaint {
            $this->states->transition(
                $complaint->lead,
                $complaint->requested_state,
                LeadTransitionReason::COMPLAINT_APPROVED,
                $reviewer,
                ['lead_complaint_id' => $complaint->getKey(), 'buyer_tenant_id' => $complaint->buyer_tenant_id],
            );

            // Zurueck kommt, was der Kauf gekostet hat: ein Guthaben.
            $this->credits->refund(
                $complaint->buyer,
                PurchaseLead::CREDITS_PER_LEAD,
                $complaint,
            );

            $complaint->status = ComplaintStatus::APPROVED;
            $complaint->reviewed_by = $reviewer->getKey();
            $complaint->reviewed_at = now();
            $complaint->decision_note = $note;
            $complaint->save();

            return $complaint;
        });
    }

    /**
     * Lehnt eine Reklamation ab. Der Lead bleibt verkauft; nach Ablauf der
     * Frist wird er automatisch `erreicht` (siehe settleElapsed()).
     */
    public function reject(LeadComplaint $complaint, User $reviewer, string $note): LeadComplaint
    {
        if (! $complaint->status->isPending()) {
            throw ComplaintNotAllowedException::alreadyDecided();
        }

        $complaint->status = ComplaintStatus::REJECTED;
        $complaint->reviewed_by = $reviewer->getKey();
        $complaint->reviewed_at = now();
        $complaint->decision_note = $note;
        $complaint->save();

        return $complaint;
    }

    /**
     * Schliesst Kaeufe ab, deren Reklamationsfrist abgelaufen ist.
     *
     * Wer nicht reklamiert hat, hat den Lead erreicht -- so herum, nicht
     * andersherum: Das Schweigen des Kaeufers ist die Zustimmung zur
     * Abrechnung. Antraege, die noch in der Pruefung liegen, bleiben
     * unberuehrt; sonst entschiede die Uhr, was ein Mensch entscheiden soll.
     *
     * @return int Zahl der abgeschlossenen Leads
     */
    public function settleElapsed(): int
    {
        $deadline = now()->subDays((int) config('funnel.call.deadline_days'));
        $settled = 0;

        $purchases = LeadPurchase::query()
            ->where('purchased_at', '<=', $deadline)
            ->whereDoesntHave('complaint', static fn ($complaint) => $complaint->where('status', ComplaintStatus::PENDING->value))
            ->with('lead')
            ->orderBy('id')
            ->get();

        foreach ($purchases as $purchase) {
            $lead = $purchase->lead;

            if ($lead === null || $lead->lead_state !== LeadState::VERKAUFT) {
                continue;
            }

            $this->states->transition(
                $lead,
                LeadState::ERREICHT,
                LeadTransitionReason::COMPLAINT_PERIOD_ELAPSED,
                null,
                ['lead_purchase_id' => $purchase->getKey()],
            );

            $settled++;
        }

        return $settled;
    }

    /**
     * Reklamationsquote eines Kaeufers: anerkannte Reklamationen je Kauf
     * (FB-060 wertet sie aus).
     *
     * Gezaehlt werden nur anerkannte -- ein abgelehnter Antrag sagt nichts
     * ueber die Qualitaet der Leads, nur ueber die Erwartung des Kaeufers.
     */
    public function approvedComplaintRateFor(Tenant $buyer): float
    {
        $purchases = LeadPurchase::query()->where('buyer_tenant_id', $buyer->getKey())->count();

        $approved = LeadComplaint::query()
            ->where('buyer_tenant_id', $buyer->getKey())
            ->where('status', ComplaintStatus::APPROVED->value)
            ->count();

        // Ohne Kaeufe gibt es keine Quote -- nicht null Prozent, sondern
        // schlicht nichts, worauf sich eine Quote beziehen koennte.
        return $purchases === 0 ? 0 : $approved / $purchases;
    }

    /**
     * @throws ComplaintNotAllowedException
     */
    private function guard(LeadPurchase $purchase, Tenant $buyer, LeadState $requestedState, string $reason): void
    {
        if ((int) $purchase->buyer_tenant_id !== (int) $buyer->getKey()) {
            throw ComplaintNotAllowedException::notYourPurchase();
        }

        if (! in_array($requestedState, [LeadState::UNERREICHBAR, LeadState::UNGUELTIG], true)) {
            throw ComplaintNotAllowedException::unsupportedState();
        }

        if (trim($reason) === '') {
            throw ComplaintNotAllowedException::reasonRequired();
        }

        if ($purchase->lead?->lead_state !== LeadState::VERKAUFT) {
            throw ComplaintNotAllowedException::leadAlreadySettled();
        }

        $deadline = $purchase->purchased_at?->copy()->addDays((int) config('funnel.call.deadline_days'));

        if ($deadline !== null && $deadline->isPast()) {
            throw ComplaintNotAllowedException::deadlineElapsed();
        }

        if (LeadComplaint::query()->where('lead_purchase_id', $purchase->getKey())->exists()) {
            throw ComplaintNotAllowedException::alreadyFiled();
        }
    }
}
