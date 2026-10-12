<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Onboarding;

use App\Constants\BuyerRadius;
use App\Constants\BuyerRegion;
use App\Constants\OnboardingStep;
use App\Livewire\Portal\Concerns\InteractsWithPortalTenant;
use App\Models\BuyerProfile;
use App\Services\Onboarding\OnboardingService;
use App\Services\Onboarding\RegionStatsService;
use App\Support\Geo\PlzDirectory;
use App\Support\Geo\SimpleProjection;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Wizard-Schritt 2: die Region (LP-ONBOARD-009).
 *
 * Die Komponente sitzt in der Karte der Wizard-Huelle (LP-ONBOARD-007) und
 * haelt drei Felder: die Art der Einschraenkung, die Postleitzahl und den
 * Umkreis. Ueberschrift, Knoepfe und der Weg zum naechsten Schritt gehoeren
 * der Huelle -- "Weiter" schickt `onboarding-step-submit` hierher, und erst
 * wenn hier gespeichert ist, meldet `onboarding-step-saved` zurueck.
 *
 * **Deutschlandweit ist die Vorgabe und bleibt es.** Nicht aus Bequemlichkeit:
 * Ein Kaeufer, der im ersten Durchlauf auf 25 Kilometer einschraenkt, sieht
 * einen fast leeren Marktplatz und haelt widileads fuer leer. Die zweite
 * Karte ist deshalb ausdruecklich die Ausnahme, und der Umkreis zeigt in
 * derselben Zeile, was er kostet: die Zahl der Anfragen, die darin uebrig
 * bleiben.
 *
 * **Die Karte rechnet nicht mit, sie zeichnet.** Pin und Kreis stehen in
 * viewBox-Einheiten aus SimpleProjection -- hier fuer den ersten Seitenaufbau,
 * danach setzt sie resources/js/modules/germany-map.js im Browser. Die Zahl
 * unter der Karte kommt dagegen immer vom Server (RegionStatsService): Sie
 * haengt an den Leads der letzten vier Wochen, und die kennt der Browser
 * nicht. Die Felder laufen deshalb mit `wire:model.live.debounce.300ms` --
 * der Kreis folgt dem Regler sofort, die Zahl ein Drittel Sekunde spaeter.
 *
 * **Was gespeichert wird:** region, postal_code und radius_km in den
 * Kaufkriterien (LP-PORTAL-011). Die `postal_prefixes` bleiben unberuehrt --
 * sie sind das Feld, das der Kaeufer dort von Hand pflegt, und der Umkreis
 * eines ganzen Bundeslandes als Praefixwolke hineinzuschreiben waere eine
 * Angabe, die er nicht mehr zurueckdrehen kann. Sind beide gesetzt, gelten sie
 * zusammen (UND) -- der LeadMatcher wertet beide aus.
 */
class StepRegion extends Component
{
    use InteractsWithPortalTenant;

    /**
     * `nationwide` oder `radius`.
     *
     * Nicht gesperrt: Das sind die Felder dieses Schritts, der Browser darf
     * sie setzen. Was nicht passt, faellt beim Speichern durch die Pruefung.
     */
    public string $region = BuyerRegion::NATIONWIDE->value;

    /** Die Postleitzahl des Mittelpunkts, fuenf Ziffern. */
    public string $postalCode = '';

    /**
     * Die Stufe des Schiebereglers (0 bis 4), nicht der Umkreis selbst.
     *
     * Der Regler laeuft ueber die Stufen und nicht ueber Kilometer: Die
     * Stufen 10/25/50/100/200 liegen nicht gleich weit auseinander, und ein
     * Regler mit `min=10 max=200` haette zwischen 100 und 200 die halbe
     * Schiene fuer einen einzigen Schritt. Die Kilometer stehen daneben als
     * grosse Zahl, der Index taucht nirgends auf.
     */
    public int $radiusIndex = BuyerRadius::DEFAULT_INDEX;

    private ?BuyerProfile $profile = null;

    public function mount(): void
    {
        $profile = $this->profile();

        $this->region = ($profile->region ?? BuyerRegion::NATIONWIDE)->value;
        $this->postalCode = (string) ($profile->postal_code ?? '');
        $this->radiusIndex = BuyerRadius::indexFor($profile->radius_km ?? BuyerRadius::DEFAULT_KM);
    }

    /**
     * Die Stufen des Schiebereglers.
     *
     * Die Stufen selbst stehen in App\Constants\BuyerRadius -- Wizard,
     * Kaufkriterien im Portal und das Filament-Formular lesen dieselbe Liste.
     *
     * @return list<int>
     */
    public function radiusSteps(): array
    {
        return BuyerRadius::steps();
    }

    /**
     * "Weiter" aus der Huelle.
     *
     * Erst pruefen, dann die Kaufkriterien schreiben, dann abhaken, dann
     * zurueckmelden -- dieselbe Reihenfolge wie in Schritt 1: Ein abgehakter
     * Schritt ohne gespeicherte Region liesse den Kaeufer mit Kriterien
     * zurueck, zu denen er nicht mehr gefragt wird.
     */
    #[On('onboarding-step-submit')]
    public function save(): void
    {
        $region = BuyerRegion::fromValue($this->region);
        $this->region = $region->value;

        if ($region === BuyerRegion::RADIUS && $this->resolvedPostalCode() === null) {
            $this->addError('postalCode', __('portal.onboarding.region.errors.unknown_plz'));

            return;
        }

        $this->resetErrorBag();

        $isRadius = $region === BuyerRegion::RADIUS;
        $radiusKm = $this->radiusKm();

        // Deutschlandweit raeumt Postleitzahl und Umkreis weg. Stehen zu
        // lassen, was nicht mehr gilt, waere die Stelle, an der eine spaetere
        // Auswertung auf einen Umkreis trifft, den der Kaeufer abgewaehlt hat.
        $this->profile()->update([
            'region' => $region->value,
            'postal_code' => $isRadius ? $this->resolvedPostalCode() : null,
            'radius_km' => $isRadius ? $radiusKm : null,
        ]);

        app(OnboardingService::class)->complete(
            $this->portalTenant(),
            OnboardingStep::REGION_SELECTED,
            $this->portalUser(),
            [
                'region' => $region->value,
                'postal_code' => $isRadius ? $this->resolvedPostalCode() : null,
                'radius_km' => $isRadius ? $radiusKm : null,
            ],
        );

        $this->dispatch('onboarding-step-saved');
    }

    public function render(): View
    {
        $isRadius = BuyerRegion::fromValue($this->region) === BuyerRegion::RADIUS;
        $centre = $isRadius ? $this->centre() : null;
        $radius = $this->radiusKm();

        $point = $centre === null
            ? null
            : SimpleProjection::project($centre['lat'], $centre['lon']);

        return view('livewire.portal.onboarding.step-region', [
            'isRadius' => $isRadius,
            'steps' => $this->radiusSteps(),
            'radius' => $radius,
            'place' => $centre['place'] ?? null,
            // Die unbekannte Postleitzahl bleibt bis zum Klick auf "Weiter"
            // ohne rote Meldung: Wer gerade die dritte von fuenf Ziffern
            // tippt, hat noch nichts falsch gemacht.
            'isUnknownPlz' => $isRadius && $this->postalCode !== '' && $centre === null,
            'weekly' => $this->weekly($isRadius, $centre, $radius),
            'mapX' => $point['x'] ?? null,
            'mapY' => $point['y'] ?? null,
            'mapRadius' => SimpleProjection::kilometresToUnits($radius),
            'showArea' => $isRadius && $point !== null,
            // Dieselben Konstanten im Browser: germany-map.js rechnet den
            // Radius damit selbst, waehrend der Finger noch auf dem Regler
            // liegt. Den Mittelpunkt bekommt er nicht -- den schlaegt der
            // Server nach, und das ganze Verzeichnis waeren 270 Kilobyte je
            // Seitenaufbau, um einen Punkt zu zeichnen.
            'projectionScale' => SimpleProjection::SCALE,
            'projectionKmPerDegree' => SimpleProjection::KM_PER_DEGREE,
        ]);
    }

    /**
     * Die Zahl unter der Karte.
     *
     * @param  array{postal_code: string, place: string, lat: float, lon: float}|null  $centre
     */
    private function weekly(bool $isRadius, ?array $centre, int $radius): int
    {
        $stats = app(RegionStatsService::class);
        $funnelIds = $this->profile()->funnelIds();

        if (! $isRadius) {
            return $stats->nationwideWeekly($funnelIds);
        }

        if ($centre === null) {
            return 0;
        }

        return $stats->radiusWeekly($funnelIds, $centre['postal_code'], $radius);
    }

    /**
     * Die eingegebene Postleitzahl, sofern wir sie kennen -- sonst null.
     */
    private function resolvedPostalCode(): ?string
    {
        return $this->centre()['postal_code'] ?? null;
    }

    /**
     * @return array{postal_code: string, place: string, lat: float, lon: float}|null
     */
    private function centre(): ?array
    {
        return app(PlzDirectory::class)->find($this->postalCode);
    }

    /**
     * Der gewaehlte Umkreis in Kilometern.
     *
     * Der Index kommt aus dem Browser und wird hier eingefangen: Ein Wert
     * ausserhalb der Stufen ist keine Fehlermeldung wert, er landet auf der
     * naechstgelegenen Stufe.
     */
    private function radiusKm(): int
    {
        $this->radiusIndex = max(0, min(count(BuyerRadius::steps()) - 1, $this->radiusIndex));

        return BuyerRadius::kmForIndex($this->radiusIndex);
    }

    private function profile(): BuyerProfile
    {
        return $this->profile ??= BuyerProfile::query()->firstOrCreate([
            'tenant_id' => $this->portalTenant()->getKey(),
        ]);
    }
}
