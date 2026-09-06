<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Lead-Detail des Betreibers (FB-034).
 *
 * Zeigt, woraus der Lead entstanden ist: Antworten, Punktzahl, Ergebnis,
 * Herkunft und das vollstaendige Zustandsprotokoll. Der Kontaktblock ist die
 * Komponente aus FB-032 -- die Entscheidung, ob Klartext oder verdeckt, faellt
 * dort und nicht hier.
 *
 * Der Mandantenbezug wird ausdruecklich geprueft: Die Lead-Kennung steht in der
 * Adresszeile, und ein Lead eines fremden Mandanten darf auch bei geratener
 * Kennung nicht erscheinen.
 */
class LeadDetail extends Component
{
    public int $leadId;

    public function render(): View
    {
        $tenant = $this->tenant();
        $viewer = $this->viewer();

        $lead = Lead::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->with(['answers', 'stateLog.actor', 'funnel', 'publicSession', 'duplicateOf'])
            ->find($this->leadId);

        if ($lead === null) {
            return view('livewire.dashboard.lead-detail', ['lead' => null]);
        }

        return view('livewire.dashboard.lead-detail', [
            'lead' => $lead,
            'presenter' => new LeadPresenter($lead, $viewer),
            'answers' => $lead->answers
                ->reject(static fn (LeadAnswer $answer): bool => $answer->isPersonal())
                ->values(),
        ]);
    }

    private function tenant(): Tenant
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }

    private function viewer(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
