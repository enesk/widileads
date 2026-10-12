<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Onboarding;

use App\Constants\OnboardingStep;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Livewire;

/**
 * Die Huelle des Einrichtungs-Wizards (LP-ONBOARD-007).
 *
 * **Sie kennt kein einziges Feld.** Hier stehen die Schritt-Leiste, die
 * Illustration, die Karte, die beiden Knoepfe und der Weg von einem Schritt
 * zum naechsten. Was im Schritt ausgefuellt wird -- Branchen
 * (LP-ONBOARD-008), Region (009), Zahlungsweise (010) -- gehoert je einer
 * eigenen Livewire-Komponente, die in die Karte gesetzt wird. Die Trennung ist
 * nicht Geschmack: Sonst waechst hier ein Formular mit drei Regelwerken,
 * dessen Validierung von der Hin- und Herschalterei nicht mehr zu trennen ist.
 *
 * **Wie die Schritte zusammenspielen.** Der Knopf "Weiter" steht in der
 * Huelle, die Pruefung im Schritt. Ein Klick schickt deshalb das Ereignis
 * `onboarding-step-submit` an die Schritt-Komponente; sie validiert, speichert
 * und hakt den Schritt mit OnboardingService::complete() ab. Danach meldet sie
 * `onboarding-step-saved`, und erst dann geht die Huelle weiter. Scheitert die
 * Pruefung, bleibt die Meldung aus und der Kaeufer steht weiter auf seinem
 * Schritt -- genau dort, wo seine Fehlermeldungen stehen.
 *
 * Solange eine Schritt-Komponente noch nicht gebaut ist, zeigt die Karte einen
 * Platzhalter, und "Weiter" hakt den Schritt selbst ab. Das ist Absicht: Die
 * Huelle muss ohne die drei Folgetickets durchlaufbar und abnehmbar sein.
 *
 * **Der Schritt steht im Pfad** und nicht im Zustand der Komponente. Jeder
 * Wechsel ist deshalb eine echte Navigation: Zurueck und Vorwaerts des
 * Browsers tun, was sie sagen, ein Neuladen landet auf demselben Schritt, und
 * der Fokus-Sprung auf die neue H1 haengt an einem Seitenaufbau statt an einem
 * Morph (resources/js/modules/wizard.js).
 *
 * **Was nicht erreichbar ist:** ein Schritt hinter dem aktuellen -- wer
 * `/onboarding/payment` aufruft, ohne die Branchen gewaehlt zu haben, landet
 * auf dem offenen Schritt. Zurueck zu einem erledigten Schritt ist dagegen
 * immer erlaubt und ueberschreibt nichts; abgehakt bleibt abgehakt, bis der
 * Schritt selbst neu speichert.
 */
#[Layout('components.layouts.portal-focus')]
class Wizard extends Component
{
    use InteractsWithPortalTenant;

    /**
     * Die Livewire-Namen der drei Schritt-Komponenten, nach Adressstueck.
     *
     * Als Livewire-Name und nicht als ::class, weil es die drei Klassen noch
     * nicht gibt: Ein ::class auf eine fehlende Klasse liesse die statische
     * Analyse auflaufen, obwohl genau damit gerechnet wird. Was fehlt, zeigt
     * die Karte als Platzhalter.
     *
     * @var array<string, string>
     */
    private const STEP_COMPONENTS = [
        'industries' => 'portal.onboarding.step-industries',
        'region' => 'portal.onboarding.step-region',
        'payment' => 'portal.onboarding.step-payment',
    ];

    /**
     * Das Adressstueck des angezeigten Schritts, wie es im Pfad steht.
     */
    #[Locked]
    public string $step = '';

    public function mount(string $step): void
    {
        $this->step = $step;

        $redirect = $this->redirectToOpenStep();

        if ($redirect !== null) {
            $this->redirect($redirect, navigate: false);
        }
    }

    /**
     * "Weiter" bzw. "Fertig".
     *
     * Gespeichert wird nicht hier, sondern im Schritt -- siehe
     * Klassenkommentar. Ohne Schritt-Komponente hakt die Huelle selbst ab.
     */
    public function submitStep(): void
    {
        $component = $this->stepComponent();

        if ($component === null) {
            $this->completeStep();
            $this->advance();

            return;
        }

        $this->dispatch('onboarding-step-submit')->to($component);
    }

    /**
     * Der Schritt hat gespeichert und abgehakt -- weiter zum naechsten.
     */
    #[On('onboarding-step-saved')]
    public function advance(): void
    {
        $next = $this->nextStepSlug();

        if ($next === null) {
            // Der letzte Schritt. Den Abschluss hat OnboardingService beim
            // Abhaken festgeschrieben, hier bleibt der Weg ins Dashboard.
            $this->redirect($this->dashboardUrl(), navigate: false);

            return;
        }

        $this->redirect($this->stepUrl($next), navigate: false);
    }

    /**
     * "Zurueck". Schreibt nichts -- ein erledigter Schritt bleibt erledigt.
     *
     * Auf dem ersten Schritt gibt es den Knopf nicht: Dahinter liegt das
     * Dashboard, und die Gate-Middleware schickte den Kaeufer von dort
     * umgehend hierher zurueck. Ein Knopf, der auf seine eigene Seite fuehrt,
     * sieht aus wie ein Fehler. Wer den Wizard verlassen will, nimmt "Spaeter
     * einrichten".
     */
    public function back(): void
    {
        $previous = $this->previousStepSlug();

        if ($previous === null) {
            return;
        }

        $this->redirect($this->stepUrl($previous), navigate: false);
    }

    /**
     * "Spaeter einrichten".
     *
     * Der Wizard sperrt danach nicht mehr, abgeschlossen ist er aber nicht:
     * Die offenen Pflichtschritte stehen weiter in der Aktivierungskarte des
     * Dashboards (LP-ONBOARD-011).
     */
    public function skip(OnboardingService $onboarding): void
    {
        $onboarding->skipWizard($this->portalTenant());

        $this->redirect($this->dashboardUrl(), navigate: false);
    }

    public function render(): View
    {
        $step = $this->currentStep();
        $position = $this->position();
        $total = count(OnboardingStep::wizardSteps());

        $counter = __('portal.onboarding.wizard.counter', [
            'step' => $position,
            'total' => $total,
        ]);

        return view('livewire.portal.onboarding.wizard', [
            'stepSlug' => $this->step,
            'stepKey' => $step?->value,
            'position' => $position,
            'total' => $total,
            'counter' => $counter,
            'isLastStep' => $this->nextStepSlug() === null,
            'canGoBack' => $this->previousStepSlug() !== null,
            'canSkip' => $position < $total,
            'stepComponent' => $this->stepComponent(),
            'completedSlugs' => $this->completedSlugs(),
        ])->title(__('portal.onboarding.wizard.title', [
            'counter' => $counter,
            'step' => __('portal.onboarding.steps.'.$this->step.'.label'),
        ]));
    }

    /**
     * Der angezeigte Schritt als Enum.
     */
    private function currentStep(): ?OnboardingStep
    {
        return OnboardingStep::fromWizardSlug($this->step);
    }

    /**
     * Die Nummer des angezeigten Schritts, bei 1 beginnend.
     */
    private function position(): int
    {
        $index = array_search($this->step, OnboardingStep::wizardSlugs(), strict: true);

        return $index === false ? 1 : $index + 1;
    }

    /**
     * Die erledigten Pflichtschritte als Adressstuecke -- das Futter der
     * Schritt-Leiste.
     *
     * @return list<string>
     */
    private function completedSlugs(): array
    {
        $completed = [];

        foreach (app(OnboardingService::class)->completedSteps($this->portalTenant()) as $step) {
            $slug = $step->wizardSlug();

            if ($slug !== null) {
                $completed[] = $slug;
            }
        }

        return $completed;
    }

    /**
     * Die Komponente des angezeigten Schritts, oder null, solange sie noch
     * nicht gebaut ist.
     */
    private function stepComponent(): ?string
    {
        $component = self::STEP_COMPONENTS[$this->step] ?? null;

        return $component !== null && Livewire::exists($component) ? $component : null;
    }

    private function completeStep(): void
    {
        $step = $this->currentStep();

        if ($step === null) {
            return;
        }

        app(OnboardingService::class)->complete(
            $this->portalTenant(),
            $step,
            $this->portalUser(),
        );
    }

    private function nextStepSlug(): ?string
    {
        return OnboardingStep::wizardSlugs()[$this->position()] ?? null;
    }

    private function previousStepSlug(): ?string
    {
        return OnboardingStep::wizardSlugs()[$this->position() - 2] ?? null;
    }

    /**
     * Die Adresse des offenen Schritts, wenn der aufgerufene zu weit vorn
     * liegt -- sonst null.
     *
     * Ein erledigter oder der offene Schritt bleibt stehen; alles dahinter
     * waere ein Schritt, dessen Voraussetzungen fehlen.
     */
    private function redirectToOpenStep(): ?string
    {
        $open = app(OnboardingService::class)->currentWizardStep($this->portalTenant());

        if ($open === null) {
            // Der letzte Schritt bleibt nach dem Abschluss erreichbar: Er gibt
            // in den Checkout ab (LP-ONBOARD-010), und wer dort abbricht,
            // kommt ueber den Rueckweg der Aufladeseite hierher. Ein Sprung
            // ins Dashboard wuerde diesen Abbruch wie einen Fehler aussehen
            // lassen -- und die Wahl waere nicht mehr zu aendern.
            if ($this->nextStepSlug() === null && $this->portalTenant()->onboarding_completed_at !== null) {
                return null;
            }

            // Sonst: Der Wizard ist vorbei, und ein zweiter Durchlauf wuerde
            // den Kaeufer nur aufhalten.
            return $this->dashboardUrl();
        }

        $slugs = OnboardingStep::wizardSlugs();
        $openIndex = array_search($open->wizardSlug(), $slugs, strict: true);

        if ($openIndex === false || $this->position() - 1 <= $openIndex) {
            return null;
        }

        return $this->stepUrl($slugs[$openIndex]);
    }

    private function stepUrl(string $slug): string
    {
        return route('portal.onboarding', [
            'tenant' => $this->portalTenant()->uuid,
            'step' => $slug,
        ]);
    }

    private function dashboardUrl(): string
    {
        return route('portal.overview', ['tenant' => $this->portalTenant()->uuid]);
    }
}
