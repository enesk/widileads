<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Constants\AuditAction;
use App\Constants\BuyerLeadFeedback;
use App\Constants\FunnelFieldKey;
use App\Constants\LeadState;
use App\Exceptions\ComplaintNotAllowedException;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\AuditLogger;
use App\Services\LeadComplaintService;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Meine Leads" -- die gekauften Leads eines Kaeufers (FB-057).
 *
 * Reines Livewire, keine Filament-Komponenten. Kontaktdaten kommen wie ueberall
 * ausschliesslich ueber den LeadPresenter; dass sie hier im Klartext stehen,
 * entscheidet nicht diese Komponente, sondern der LeadContactResolver anhand
 * des Kaufbelegs (FB-032, FB-054).
 *
 * Gezeigt werden ausschliesslich die Kaeufe des aktiven Mandanten -- der
 * Kaufbeleg gehoert dem Kaeufer, waehrend der Lead dem Betreiber gehoert.
 * Deshalb wird hier ausdruecklich auf `buyer_tenant_id` eingeschraenkt und
 * nicht auf einen Mandanten-Scope vertraut, der auf die falsche Spalte zielte.
 */
class PurchasedLeads extends Component
{
    use WithPagination;

    /**
     * Nur Kaeufe ohne Rueckmeldung anzeigen.
     */
    public bool $onlyWithoutFeedback = false;

    public ?string $selectedFeedback = null;

    /**
     * Begruendung je Kauf, waehrend der Kaeufer sie tippt.
     *
     * @var array<int, string>
     */
    public array $complaintReason = [];

    /**
     * Beantragter Zustand je Kauf.
     *
     * @var array<int, string>
     */
    public array $complaintState = [];

    public ?string $complaintError = null;

    public function setFeedback(int $purchaseId, string $feedback): void
    {
        $value = BuyerLeadFeedback::tryFrom($feedback);

        if ($value === null) {
            return;
        }

        $purchase = $this->purchases()->firstWhere('id', $purchaseId);

        if (! $purchase instanceof LeadPurchase) {
            return;
        }

        $purchase->update([
            'buyer_feedback' => $value,
            'buyer_feedback_at' => now(),
        ]);
    }

    /**
     * Reklamiert einen Kauf (FB-058).
     *
     * Die Komponente entscheidet nichts: Der Antrag geht in die Pruefliste, ein
     * Mensch sieht ihn an. Was hier abgefangen wird, sind die alltaeglichen
     * Ausgaenge -- Frist abgelaufen, schon reklamiert, Lead nicht mehr
     * verkauft. Alles Hinweise, keine Fehler.
     */
    public function fileComplaint(int $purchaseId): void
    {
        $purchase = $this->purchases()->firstWhere('id', $purchaseId);

        if (! $purchase instanceof LeadPurchase) {
            return;
        }

        $state = LeadState::tryFrom((string) ($this->complaintState[$purchaseId] ?? ''));

        try {
            app(LeadComplaintService::class)->file(
                $purchase,
                $this->tenant(),
                $state ?? LeadState::UNERREICHBAR,
                (string) ($this->complaintReason[$purchaseId] ?? ''),
            );
        } catch (ComplaintNotAllowedException $exception) {
            $this->complaintError = $exception->getMessage();

            return;
        }

        $this->complaintError = null;
        unset($this->complaintReason[$purchaseId], $this->complaintState[$purchaseId]);
    }

    public function updatedOnlyWithoutFeedback(): void
    {
        $this->resetPage();
    }

    /**
     * Gibt die eigenen Kaeufe als CSV aus.
     *
     * Der Export enthaelt Kontaktdaten im Klartext und wird deshalb im
     * Audit-Log festgehalten (FB-005, AuditAction::DATA_EXPORTED). Wer Daten
     * aus dem System traegt, hinterlaesst eine Spur -- das ist der Sinn des
     * Protokolls und keine Zusatzfunktion.
     */
    public function exportCsv(): StreamedResponse
    {
        $tenant = $this->tenant();
        $viewer = $this->viewer();
        $purchases = $this->purchases();

        app(AuditLogger::class)->log(
            AuditAction::DATA_EXPORTED,
            null,
            ['subject' => 'purchased_leads', 'count' => $purchases->count()],
            $tenant,
        );

        $rows = $purchases->map(function (LeadPurchase $purchase) use ($viewer): array {
            $presenter = new LeadPresenter($purchase->lead, $viewer);

            return [
                $purchase->purchased_at?->toDateTimeString(),
                $purchase->lead->funnel?->name,
                $presenter->name(),
                $presenter->email(),
                $presenter->phone(),
                $presenter->postalCode(),
                (string) $purchase->lead->score,
                $purchase->buyer_feedback?->value,
            ];
        })->all();

        $headers = [
            __('marketplace.purchased.csv.purchased_at'),
            __('marketplace.purchased.csv.funnel'),
            __('marketplace.purchased.csv.name'),
            __('marketplace.purchased.csv.email'),
            __('marketplace.purchased.csv.phone'),
            __('marketplace.purchased.csv.postal_code'),
            __('marketplace.purchased.csv.score'),
            __('marketplace.purchased.csv.feedback'),
        ];

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Byte Order Mark, damit Excel die Umlaute richtig liest.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, 'meine-leads.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $viewer = $this->viewer();
        $purchases = $this->purchases();

        return view('livewire.dashboard.purchased-leads', [
            'rows' => $this->paginate($purchases)->through(fn (LeadPurchase $purchase): array => [
                'purchase' => $purchase,
                'presenter' => new LeadPresenter($purchase->lead, $viewer),
                'qualification' => $this->qualificationAnswers($purchase),
                'complaint' => $purchase->complaint,
                'canComplain' => $this->canComplain($purchase),
            ]),
            'feedbackOptions' => BuyerLeadFeedback::options(),
            'complaintStates' => [
                LeadState::UNERREICHBAR->value => LeadState::UNERREICHBAR->label(),
                LeadState::UNGUELTIG->value => LeadState::UNGUELTIG->label(),
            ],
        ]);
    }

    /**
     * Die Qualifizierungsantworten -- ohne die reservierten Kontaktfelder.
     *
     * Der Kaeufer duerfte sie hier zwar sehen, aber die Kontaktdaten stehen
     * ohnehin schon aus dem Presenter daneben. Sie ein zweites Mal aus den
     * Rohantworten zu holen, waere ein Weg an der einen Stelle vorbei, an der
     * ueber ihre Sichtbarkeit entschieden wird.
     *
     * @return array<string, string>
     */
    private function qualificationAnswers(LeadPurchase $purchase): array
    {
        $answers = [];

        foreach ($purchase->lead->answers as $answer) {
            if (FunnelFieldKey::isReserved($answer->field_key)) {
                continue;
            }

            $value = $answer->value;

            $answers[$answer->field_key] = is_array($value)
                ? implode(', ', array_map(static fn (mixed $part): string => (string) $part, $value))
                : (string) $value;
        }

        return $answers;
    }

    /**
     * Kann dieser Kauf noch reklamiert werden?
     *
     * Nur eine Vorschau fuer die Oberflaeche -- verbindlich prueft der
     * LeadComplaintService in dem Moment, in dem der Antrag kommt.
     */
    private function canComplain(LeadPurchase $purchase): bool
    {
        if ($purchase->complaint !== null) {
            return false;
        }

        if ($purchase->lead?->lead_state !== LeadState::VERKAUFT) {
            return false;
        }

        $deadline = $purchase->purchased_at?->copy()->addDays((int) config('funnel.call.deadline_days'));

        return $deadline === null || $deadline->isFuture();
    }

    /**
     * @return Collection<int, LeadPurchase>
     */
    private function purchases(): Collection
    {
        $query = LeadPurchase::query()
            ->with([
                'complaint',
                'lead.answers',
                // Ohne Mandanten-Scope: Der Fragebogen gehoert dem Betreiber,
                // nicht dem Kaeufer. Mit Scope kaeme hier immer null heraus,
                // und der Kaeufer saehe seine eigenen Kaeufe ohne Herkunft.
                'lead.funnel' => static fn (Relation $funnel) => $funnel->withoutGlobalScope('tenant'),
            ])
            ->ofBuyer($this->tenant())
            ->orderByDesc('purchased_at');

        if ($this->onlyWithoutFeedback) {
            $query->whereNull('buyer_feedback');
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, LeadPurchase>  $purchases
     * @return LengthAwarePaginator<int, LeadPurchase>
     */
    private function paginate(Collection $purchases): LengthAwarePaginator
    {
        $perPage = (int) config('funnel.marketplace.listing.per_page');
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $purchases->forPage($page, $perPage)->values(),
            $purchases->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }

    private function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
