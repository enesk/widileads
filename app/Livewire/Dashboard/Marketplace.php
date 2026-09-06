<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Constants\FunnelFieldKey;
use App\Constants\LeadState;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerProfile;
use App\Models\Lead;
use App\Models\LeadWatchlistEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\LeadPurchaseAction;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Der Lead-Marktplatz eines Kaeufers (FB-053).
 *
 * Reines Livewire mit Tailwind und daisyUI, keine Filament-Komponenten -- die
 * Filament-Page daneben ist nur die Huelle fuer Routing und Navigation.
 *
 * **Diese Komponente maskiert nichts.** Kontaktdaten kommen ausschliesslich
 * ueber den LeadPresenter, der `Lead::contactFor()` aufruft; wer welche Fassung
 * sieht, entscheidet allein der LeadContactResolver aus FB-032. Eine zweite
 * Maskierlogik waere ein Verstoss gegen Architekturleitsatz 5 -- und der
 * Architektur-Test aus FB-042 schlaegt darauf an.
 *
 * Ebenso wenig kauft sie: Der Knopf fragt die austauschbare Zusage
 * LeadPurchaseAction. Bis FB-054 sie ersetzt, bleibt er inaktiv.
 */
class Marketplace extends Component
{
    use WithPagination;

    public string $sort = MarketplaceListing::SORT_NEWEST;

    public bool $onlyWatchlisted = false;

    /**
     * Wechselt die Vormerkung eines Leads. Sie ist eine private Notiz des
     * Kaeufers und aendert am Lead nichts -- ein vorgemerkter Lead kann
     * jederzeit von jemand anderem gekauft werden.
     */
    public function toggleWatchlist(int $leadId): void
    {
        $tenant = $this->tenant();

        $existing = LeadWatchlistEntry::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->getKey())
            ->where('lead_id', $leadId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return;
        }

        // Nur Leads, die dieser Kaeufer im Marktplatz ueberhaupt sieht -- sonst
        // liesse sich ueber die Merkliste erraten, welche Leads es gibt.
        if (! $this->visibleLeads()->contains(static fn (Lead $lead): bool => (int) $lead->getKey() === $leadId)) {
            return;
        }

        LeadWatchlistEntry::query()->create([
            'tenant_id' => $tenant->getKey(),
            'lead_id' => $leadId,
        ]);
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyWatchlisted(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $leads = $this->visibleLeads();
        $purchase = app(LeadPurchaseAction::class);
        $tenant = $this->tenant();
        $viewer = $this->viewer();

        return view('livewire.dashboard.marketplace', [
            'rows' => $this->paginate($leads)->through(fn (Lead $lead): array => [
                'lead' => $lead,
                // Kontaktdaten ausschliesslich ueber den Presenter.
                'presenter' => new LeadPresenter($lead, $viewer),
                'qualification' => $this->qualificationAnswers($lead),
                'isTaken' => $lead->lead_state === LeadState::RESERVIERT,
                'isWatchlisted' => in_array((int) $lead->getKey(), $this->watchlistedIds(), true),
                'canPurchase' => $purchase->canPurchase($tenant, $lead),
            ]),
            'purchaseAvailable' => $purchase->isAvailable(),
            'hasProfile' => $this->profile() !== null,
        ]);
    }

    /**
     * Die Qualifizierungsantworten eines Leads -- **ohne** die reservierten
     * Kontaktfelder.
     *
     * Das ist der Punkt, an dem eine Marktplatzliste am ehesten Klartext
     * ausplaudert: `lead_answers` enthaelt auch die Antworten auf `email`,
     * `telefon` und `plz`. Wer sie ungefiltert ausgibt, umgeht die Maskierung,
     * ohne je eine Kontaktspalte anzufassen. Kontaktdaten kommen deshalb
     * ausschliesslich aus dem Presenter, Antworten hier nur, soweit sie keine
     * sind.
     *
     * @return array<string, string>
     */
    private function qualificationAnswers(Lead $lead): array
    {
        $answers = [];

        foreach ($lead->answers as $answer) {
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
     * @return Collection<int, Lead>
     */
    private function visibleLeads(): Collection
    {
        return app(MarketplaceListing::class)->for(
            $this->tenant(),
            $this->profile(),
            $this->sort,
            $this->onlyWatchlisted,
        );
    }

    /**
     * Der Matcher ist eine reine Funktion und laeuft in PHP, deshalb wird nach
     * dem Filtern seitenweise ausgegeben. Wie viele Leads dabei hoechstens
     * geprueft werden, steht in config('funnel.marketplace.listing').
     *
     * @param  Collection<int, Lead>  $leads
     * @return LengthAwarePaginator<int, Lead>
     */
    private function paginate(Collection $leads): LengthAwarePaginator
    {
        $perPage = (int) config('funnel.marketplace.listing.per_page');
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $leads->forPage($page, $perPage)->values(),
            $leads->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * @return array<int, int>
     */
    private function watchlistedIds(): array
    {
        return LeadWatchlistEntry::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant()->getKey())
            ->pluck('lead_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function profile(): ?BuyerProfile
    {
        return BuyerProfile::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant()->getKey())
            ->first();
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
