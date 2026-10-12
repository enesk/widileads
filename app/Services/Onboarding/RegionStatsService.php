<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Constants\LeadState;
use App\Marketplace\MarketplaceCatalog;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use App\Support\Geo\PlzDirectory;
use Illuminate\Support\Facades\Cache;

/**
 * Wie viele Anfragen es zuletzt in einem Gebiet gab (LP-ONBOARD-009).
 *
 * Die Zeile unter der Karte beantwortet die einzige Frage, die der zweite
 * Wizard-Schritt offen laesst: "Lohnt sich mein Umkreis?" Sie nennt dieselbe
 * Art Zahl wie die Branchenkacheln davor -- gemittelt ueber vier Wochen,
 * gerundet, unterhalb der Mindestzahl gar nicht genannt. Die Schwellen stehen
 * nicht doppelt in der Konfiguration, sondern kommen aus
 * `funnel.onboarding.industry_stats`: Zwei Schritte hintereinander mit
 * verschieden gerundeten Zahlen waeren fuer den Kaeufer ein Widerspruch,
 * kein Detail.
 *
 * **Deutschlandweit ist keine zweite Rechnung.** Dort kommt die Summe aus
 * IndustryStatsService::weeklyTotalFor() -- genau die Zahl, die der Kaeufer
 * einen Schritt vorher auf seinen Kacheln zusammengezaehlt hat. Eine eigene
 * Abfrage haette hier eine andere Zahl ergeben koennen, und der Kaeufer haette
 * zu Recht gefragt, welche von beiden stimmt.
 *
 * **Im Umkreis: eine Abfrage, beliebig viele Schieberegler-Bewegungen.**
 * Gemerkt wird nicht das Ergebnis fuer einen Umkreis, sondern die Verteilung
 * der Anfragen auf Postleitzahlen (`region_weekly_by_plz`, je Branchenauswahl
 * ein Schluessel). Den Umkreis schneidet danach PHP aus dieser Verteilung --
 * deshalb kostet ein Zug am Regler von 25 auf 200 Kilometer keine einzige
 * weitere Abfrage.
 *
 * **Was freigegeben wird:** eine gerundete Zahl je Gebiet. Welcher Betreiber
 * wie viele Anfragen liefert, bleibt wie in IndustryStatsService drinnen.
 */
class RegionStatsService
{
    /**
     * Der Schluesselstamm der gemerkten Verteilung. Die gewaehlten Branchen
     * haengen als Hash daran.
     */
    public const CACHE_KEY = 'region_weekly_by_plz';

    public function __construct(
        private readonly PlzDirectory $plz,
        private readonly IndustryStatsService $industries,
    ) {}

    /**
     * Anfragen je Woche in ganz Deutschland, in den gewaehlten Branchen.
     *
     * @param  list<int>  $funnelIds
     */
    public function nationwideWeekly(array $funnelIds): int
    {
        return $this->industries->weeklyTotalFor($this->funnelIds($funnelIds));
    }

    /**
     * Anfragen je Woche im Umkreis einer Postleitzahl, in den gewaehlten
     * Branchen.
     *
     * Unbekannte Postleitzahl heisst 0 -- und nicht "ganz Deutschland": Der
     * Kaeufer hat ausdruecklich eingeschraenkt, und eine grosse Zahl neben
     * einer Eingabe, die wir nicht lesen konnten, waere eine Zusage auf
     * nichts.
     *
     * @param  list<int>  $funnelIds
     */
    public function radiusWeekly(array $funnelIds, string $postalCode, int $radiusKm): int
    {
        $centre = $this->plz->normalise($postalCode);

        if ($centre === null || ! $this->plz->exists($centre)) {
            return 0;
        }

        $perWeek = 0.0;
        $weeks = $this->windowDays() / 7;

        foreach ($this->leadsByPostalCode($this->funnelIds($funnelIds)) as $code => $leads) {
            $distance = $this->plz->distanceTo($centre, (string) $code);

            if ($distance !== null && $distance <= $radiusKm) {
                $perWeek += $leads / $weeks;
            }
        }

        return $this->round($perWeek);
    }

    /**
     * Wirft die gemerkten Verteilungen weg.
     *
     * Je Branchenauswahl ein Schluessel -- und damit kein Weg, sie einzeln
     * aufzuzaehlen. Verworfen wird deshalb ueber die Generation: Der
     * Schluessel traegt sie, ein Hochzaehlen macht alle alten Eintraege
     * unerreichbar, und sie laufen nach ihrer Stunde von selbst ab.
     */
    public function forget(): void
    {
        Cache::increment(self::CACHE_KEY.':generation');
    }

    /**
     * Anfragen je Postleitzahl im Zeitfenster, eine Stunde gemerkt.
     *
     * Gezaehlt wird wie in IndustryStatsService, was im Marktplatz angekommen
     * ist: kein unfreigegebener, kein verworfener, kein doppelter Lead. Leads
     * ohne Postleitzahl fallen heraus -- sie liegen nirgends.
     *
     * @param  list<int>  $funnelIds
     * @return array<string, int>
     */
    private function leadsByPostalCode(array $funnelIds): array
    {
        if ($funnelIds === []) {
            return [];
        }

        $key = sprintf(
            '%s:%d:%s',
            self::CACHE_KEY,
            (int) Cache::get(self::CACHE_KEY.':generation', 0),
            md5(implode(',', $funnelIds)),
        );

        /** @var array<string, int> $counts */
        $counts = Cache::remember(
            $key,
            now()->addMinutes((int) config('funnel.onboarding.industry_stats.cache_minutes')),
            fn (): array => Lead::query()
                // Die Leads gehoeren den Betreibern, gelesen wird im Kontext
                // des Kaeufers -- ohne das Abschalten des Scopes waere jede
                // Zahl null.
                ->withoutGlobalScopes(TenantScopes::names())
                ->whereIn('funnel_id', $funnelIds)
                ->whereNotIn('lead_state', [LeadState::NEU->value, LeadState::UNGUELTIG->value])
                ->whereNull('duplicate_of_lead_id')
                ->whereNotNull('postal_code')
                ->where('created_at', '>=', now()->subDays($this->windowDays()))
                ->groupBy('postal_code')
                ->selectRaw('postal_code, count(*) as leads')
                ->pluck('leads', 'postal_code')
                ->mapWithKeys(static fn (mixed $leads, mixed $code): array => [(string) $code => (int) $leads])
                ->all(),
        );

        return $counts;
    }

    /**
     * Die Auswahl auf veroeffentlichte Funnels eingeschraenkt.
     *
     * Leer heisst hier ausdruecklich leer und nicht "alle": Der Kaeufer hat
     * einen Schritt vorher Branchen gewaehlt, und eine Zahl ueber alle
     * Branchen waere nicht die, die er gleich im Marktplatz sieht.
     *
     * @param  list<int>  $funnelIds
     * @return list<int>
     */
    private function funnelIds(array $funnelIds): array
    {
        $published = array_map('intval', array_keys(app(MarketplaceCatalog::class)->publishedFunnels()));

        $selected = array_values(array_unique(array_map('intval', $funnelIds)));

        sort($selected);

        return array_values(array_filter(
            $selected,
            static fn (int $funnelId): bool => in_array($funnelId, $published, true),
        ));
    }

    private function windowDays(): int
    {
        return max(1, (int) config('funnel.onboarding.industry_stats.window_days'));
    }

    /**
     * Dieselbe Rundung wie auf den Branchenkacheln: auf ein Vielfaches, und
     * unterhalb der Mindestzahl auf 0. Die 0 ist hier kein Fehler, sondern
     * die Auskunft, auf die der amber-Hinweis unter der Karte antwortet.
     */
    private function round(float $perWeek): int
    {
        $minimum = (int) config('funnel.onboarding.industry_stats.minimum');

        if ($perWeek < $minimum) {
            return 0;
        }

        $step = max(1, (int) config('funnel.onboarding.industry_stats.rounding'));

        return (int) (round($perWeek / $step) * $step);
    }
}
