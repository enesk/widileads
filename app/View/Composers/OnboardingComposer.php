<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Tenant;
use App\Services\Onboarding\OnboardingProgress;
use App\Services\Onboarding\OnboardingService;
use Illuminate\View\View;

/**
 * Stellt dem Portal-Layout den Onboarding-Stand bereit (LP-ONBOARD-002).
 *
 * Damit haben Aktivierungskarte, Tour und Hinweisleisten den Stand ohne eigene
 * Abfrage -- und, wichtiger, alle denselben. Wuerde jede Komponente selbst
 * fragen, haette eine Seite am Ende drei Zaehlungen und drei Zeitpunkte.
 *
 * Gebunden an `components.layouts.portal-app`, also an den Rahmen des
 * angemeldeten Arbeitsbereichs. Die oeffentlichen Seiten und der
 * Fokus-Rahmen des Wizards bekommen ihn nicht: Dort gibt es keinen Mandanten
 * im Pfad.
 *
 * Die Variable heisst `$onboarding` und ist null, wenn der Stand nicht gilt --
 * kein Mandant im Pfad oder ein Verkaeufer. Blade muss also pruefen, nicht
 * raten.
 */
final class OnboardingComposer
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    public function compose(View $view): void
    {
        $view->with('onboarding', $this->progress($view));
    }

    private function progress(View $view): ?OnboardingProgress
    {
        $tenant = $view->getData()['tenant'] ?? request()->route('tenant');

        if (! $tenant instanceof Tenant) {
            return null;
        }

        // Das Onboarding ist die Kaeuferstrecke. Ein Betreiber-Mandant hat
        // weder Branchen noch Guthaben und soll keine Checkliste sehen.
        if (! $tenant->isBuyer()) {
            return null;
        }

        return $this->onboarding->progress($tenant);
    }
}
