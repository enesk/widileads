<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Services\Onboarding\OnboardingChecklist;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Die Aktivierungskarte im Dashboard (LP-ONBOARD-012).
 *
 * Sie fuehrt den Kaeufer vom Ende des Wizards bis zum ersten gefuehrten
 * Anruf. Niemand muss hier etwas bestaetigen: Die Schritte haken sich ueber
 * die Zuhoerer in app/Listeners/Onboarding ab, sobald im System das Passende
 * passiert. Diese Komponente zeigt an und blendet aus -- sie hakt nichts ab.
 *
 * **Woher der Stand kommt:** aus OnboardingService::progress(), demselben
 * Abbild, das der View-Composer ans Portal-Layout haengt (LP-ONBOARD-002). Es
 * ist je Workspace 30 Sekunden gemerkt, die Karte kostet also keine eigene
 * Abfrage. Ueber die Layout-Variable `$onboarding` laeuft es bewusst nicht:
 * Eine Livewire-Folgeanfrage geht an /livewire/update und rendert kein Layout
 * -- nach dem ersten `wire:click` waere die Variable weg. Der Weg ueber den
 * Dienst liest denselben Eintrag.
 *
 * **Sichtbarkeit**, in dieser Reihenfolge:
 *
 * 1. Aktiviert und Abschluss-Zustand schon gezeigt: nichts mehr.
 * 2. Aktiviert und noch nicht gezeigt: einmal "Du bist startklar.".
 * 3. Ausgeblendet (sieben Tage): nichts, danach wieder.
 * 4. Sonst: die Checkliste.
 *
 * **Die Zeilen selbst leitet diese Komponente nicht ab.** Das tut
 * OnboardingChecklist -- dieselbe Stelle, aus der die Onboarding-Mails ihren
 * naechsten Schritt und ihre Zahl "3 von 5" nehmen (LP-ONBOARD-014). Lag die
 * Ableitung doppelt da, landete ein sechster Schritt an einer Stelle und an
 * der anderen nicht.
 */
class OnboardingCard extends Component
{
    use InteractsWithPortalTenant;

    /** So lange bleibt die Karte nach "Ausblenden" weg. */
    private const HIDE_DAYS = 7;

    /**
     * Nach einer Aufladung oder einem Kauf stimmt die Checkliste nicht mehr.
     *
     * Dasselbe Ereignis, mit dem Marktplatz und Guthabenseite die
     * Geldanzeigen auffrischen. Die Zuhoerer in app/Listeners/Onboarding
     * laufen in der Queue -- kommt das Ereignis vor ihnen an, zeigt der
     * naechste Seitenaufbau den neuen Stand.
     */
    #[On('wallet-updated')]
    public function refreshChecklist(): void
    {
        $this->onboarding()->forgetProgress($this->portalTenant());
    }

    /**
     * "Ausblenden": sieben Tage Ruhe, danach erscheint die Karte wieder.
     */
    public function hide(): void
    {
        $this->onboarding()->hideActivationCard($this->portalTenant(), self::HIDE_DAYS);
    }

    public function render(): View
    {
        $tenant = $this->portalTenant();

        // Ein Betreiber-Mandant hat weder Branchen noch Guthaben und bekommt
        // deshalb gar keine Checkliste -- genau wie im View-Composer.
        if (! $tenant->isBuyer()) {
            return $this->blank();
        }

        $progress = $this->onboarding()->progress($tenant);

        if ($progress->activated) {
            // Der Abschluss-Zustand gilt fuer einen Besuch. Verbraucht wird er
            // hier beim Rendern: Wer die Seite neu laedt, hat ihn gesehen.
            if (! $this->onboarding()->markActivationCardSeen($tenant)) {
                return $this->blank();
            }

            return view('livewire.portal.onboarding-card', [
                'visible' => true,
                'activated' => true,
                'items' => [],
                'done' => 0,
                'total' => 0,
                'hideable' => false,
            ]);
        }

        $hiddenUntil = $tenant->onboarding_card_hidden_until;

        if ($hiddenUntil !== null && $hiddenUntil->isFuture()) {
            return $this->blank();
        }

        $items = app(OnboardingChecklist::class)->rows($tenant, $progress);

        return view('livewire.portal.onboarding-card', [
            'visible' => true,
            'activated' => false,
            'items' => $items,
            'done' => count(array_filter($items, static fn (array $item): bool => $item['done'])),
            'total' => count($items),
            'hideable' => true,
        ]);
    }

    private function blank(): View
    {
        return view('livewire.portal.onboarding-card', [
            'visible' => false,
            'activated' => false,
            'items' => [],
            'done' => 0,
            'total' => 0,
            'hideable' => false,
        ]);
    }

    private function onboarding(): OnboardingService
    {
        return app(OnboardingService::class);
    }
}
