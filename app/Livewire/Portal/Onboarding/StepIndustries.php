<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Onboarding;

use App\Constants\OnboardingStep;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\BuyerProfile;
use App\Services\Onboarding\IndustryStatsService;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Wizard-Schritt 1: die Branchen (LP-ONBOARD-008).
 *
 * Die Komponente sitzt in der Karte der Wizard-Huelle (LP-ONBOARD-007) und
 * haelt genau ein Feld: die gewaehlten Branchen. Ueberschrift, Knoepfe und der
 * Weg zum naechsten Schritt gehoeren der Huelle -- "Weiter" schickt
 * `onboarding-step-submit` hierher, und erst wenn hier gespeichert ist, meldet
 * `onboarding-step-saved` zurueck. Scheitert die Pruefung, bleibt die Meldung
 * aus und der Kaeufer steht weiter vor seinen Kacheln.
 *
 * **Eine Branche ist hier ein veroeffentlichter Funnel eines Betreibers.** Der
 * Marktplatz nennt dasselbe Merkmal "Branche" und filtert darauf
 * (`BuyerProfile::funnel_ids`), die Kaufkriterien heissen es "Fragebogen".
 * Gespeichert wird deshalb in genau dieses Feld und nicht in eine zweite
 * Spalte: Der LeadMatcher liest `funnel_ids`, und eine zweite Wahrheit
 * daneben waere eine Auswahl, die den Marktplatz nicht veraendert.
 *
 * **Die Zahl auf der Kachel kommt aus IndustryStatsService** -- gerundet und
 * eine Stunde gemerkt. Die Zeile unter dem Raster rechnet dieselben gerundeten
 * Zahlen im Browser zusammen (Alpine), damit sie bei jedem Klick ohne
 * Serveranfrage mitlaeuft. Beide Wege addieren dieselben Werte; ein zweites
 * Rechenverfahren waere die Stelle, an der Kachelsumme und Live-Zeile
 * auseinanderlaufen.
 */
class StepIndustries extends Component
{
    use InteractsWithPortalTenant;

    /**
     * Die gewaehlten Branchen als Funnel-Kennungen.
     *
     * Nicht gesperrt: Der Browser darf sie setzen, das ist das Feld dieses
     * Schritts. Was nicht im Angebot steht, wird beim Speichern verworfen.
     *
     * @var list<int>
     */
    public array $selected = [];

    /**
     * Innerhalb einer Anfrage einmal geholt. Nicht oeffentlich, wandert also
     * nicht in den Zustand der Komponente.
     *
     * @var list<array{funnel_id: int, name: string, icon: string, weekly: int}>|null
     */
    private ?array $industries = null;

    private ?BuyerProfile $profile = null;

    public function mount(): void
    {
        $this->selected = $this->preselection();
    }

    /**
     * "Weiter" aus der Huelle.
     *
     * Erst pruefen, dann die Kaufkriterien schreiben, dann abhaken, dann
     * zurueckmelden. Die Reihenfolge ist nicht Geschmack: Ein abgehakter
     * Schritt ohne gespeicherte Branchen liesse den Kaeufer mit einem leeren
     * Marktplatz zurueck, und er kaeme nicht mehr hierher.
     */
    #[On('onboarding-step-submit')]
    public function save(): void
    {
        $industries = $this->availableSelection();

        // Kein einziges Portal im Angebot: Dann waere die Pflicht "mindestens
        // eine Branche" eine Sperre, an der niemand vorbeikaeme -- der Kaeufer
        // sitzt im Wizard fest und erreicht auch die Aktivierungskarte nicht.
        // Der Schritt geht dann ohne Auswahl durch; die Kachel darueber sagt,
        // warum.
        if ($industries === [] && $this->availableFunnelIds() !== []) {
            $this->addError('selected', __('portal.onboarding.industries.error'));

            return;
        }

        $this->selected = $industries;
        $this->resetErrorBag();

        $workspace = $this->portalTenant();

        $this->profile()->update(['funnel_ids' => $industries]);

        app(OnboardingService::class)->complete(
            $workspace,
            OnboardingStep::INDUSTRIES_SELECTED,
            $this->portalUser(),
            ['industries' => $industries],
        );

        $this->dispatch('onboarding-step-saved');
    }

    public function render(): View
    {
        $industries = $this->orderedIndustries();
        $weekly = array_column($industries, 'weekly', 'funnel_id');

        return view('livewire.portal.onboarding.step-industries', [
            'industries' => $industries,
            'weekly' => $weekly,
            // Die Summe fuer den ersten Seitenaufbau; danach rechnet Alpine.
            'initialTotal' => array_sum(array_intersect_key($weekly, array_flip($this->selected))),
            'visibleTiles' => max(1, (int) config('funnel.onboarding.industry_stats.visible_tiles')),
        ]);
    }

    /**
     * Die Kacheln in ihrer Reihenfolge: die mitgebrachten Branchen zuerst,
     * danach die uebrigen nach Anfragevolumen.
     *
     * "Mitgebracht" ist die Vorauswahl aus preselection() -- im Regelfall die
     * Branche aus der Registrierung. Sie steht vorne, weil der Kaeufer sie
     * schon genannt hat und sie hier wiederfinden soll.
     *
     * @return list<array{funnel_id: int, name: string, icon: string, weekly: int}>
     */
    private function orderedIndustries(): array
    {
        $industries = $this->industries();
        $preselected = $this->preselection();

        if ($preselected === []) {
            return $industries;
        }

        $front = [];
        $rest = [];

        foreach ($industries as $industry) {
            if (in_array($industry['funnel_id'], $preselected, true)) {
                $front[] = $industry;

                continue;
            }

            $rest[] = $industry;
        }

        return [...$front, ...$rest];
    }

    /**
     * Was beim Oeffnen gewaehlt ist: die gespeicherten Kaufkriterien.
     *
     * Das ist auch die Herkunft der Branche aus der Registrierung
     * (LP-PORTAL-004): App\Services\BuyerOnboardingService::register schreibt
     * die dort gewaehlte Branche als ersten Eintrag in
     * `BuyerProfile::funnel_ids`. Eine zweite Quelle braucht es deshalb nicht
     * -- ein frisch registrierter Kaeufer findet seine Branche hier gewaehlt
     * vor, ein zurueckkehrender seine zuletzt gespeicherte Auswahl.
     *
     * Was nicht (mehr) im Angebot steht, fliegt heraus: Ein Funnel kann nach
     * der Registrierung aus der Veroeffentlichung genommen worden sein, und
     * eine Vorauswahl ohne passende Kachel waere eine Wahl, die der Kaeufer
     * nicht sieht und nicht abwaehlen kann.
     *
     * @return list<int>
     */
    private function preselection(): array
    {
        $available = $this->availableFunnelIds();

        return array_values(array_filter(
            $this->profile()->funnelIds(),
            static fn (int $funnelId): bool => in_array($funnelId, $available, true),
        ));
    }

    /**
     * Die Auswahl, auf das Angebot eingeschraenkt und ohne Doppelte.
     *
     * @return list<int>
     */
    private function availableSelection(): array
    {
        $available = $this->availableFunnelIds();

        $selected = array_map('intval', array_values($this->selected));

        return array_values(array_unique(array_filter(
            $selected,
            static fn (int $funnelId): bool => in_array($funnelId, $available, true),
        )));
    }

    /**
     * @return list<int>
     */
    private function availableFunnelIds(): array
    {
        return array_column($this->industries(), 'funnel_id');
    }

    /**
     * @return list<array{funnel_id: int, name: string, icon: string, weekly: int}>
     */
    private function industries(): array
    {
        return $this->industries ??= app(IndustryStatsService::class)->industries();
    }

    private function profile(): BuyerProfile
    {
        return $this->profile ??= BuyerProfile::query()->firstOrCreate([
            'tenant_id' => $this->portalTenant()->getKey(),
        ]);
    }
}
