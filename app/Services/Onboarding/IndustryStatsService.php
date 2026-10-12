<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Constants\LeadState;
use App\Marketplace\MarketplaceCatalog;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use Illuminate\Support\Facades\Cache;

/**
 * Wie viele Anfragen es in einer Branche zuletzt gab (LP-ONBOARD-008).
 *
 * Die Kacheln des ersten Wizard-Schritts nennen eine echte Zahl, weil eine
 * Branche ohne Zahl eine Behauptung ist. Die Zahl ist trotzdem ausdruecklich
 * eine Groessenordnung und keine Zusage:
 *
 *  - gemittelt ueber vier Wochen, damit eine einzelne Woche sie nicht kippt;
 *  - auf ein Vielfaches gerundet, weil "ca. 37" sich wie eine Zusage liest;
 *  - unterhalb der Mindestzahl gar nicht genannt -- dort steht "neu". Wer
 *    "ca. 0 Anfragen" liest, waehlt die Branche nicht, obwohl gerade dort die
 *    naechste Anfrage am wenigsten umkaempft ist.
 *
 * **Eine Abfrage je Stunde fuer die ganze Plattform.** Der Durchschnitt haengt
 * an keinem Kaeufer, deshalb liegt er unter einem einzigen Schluessel
 * (`industry_weekly_avg`) und nicht je Workspace. Eine Live-Aggregation pro
 * Seitenaufbau waere ein Gruppieren ueber alle Leads aller Betreiber -- bei
 * jedem Klick auf eine Kachel erneut.
 *
 * **Was freigegeben wird.** Namen und Kennungen der Branchen kommen aus
 * MarketplaceCatalog, der die Grenze des Freigegebenen zieht. Hier kommt genau
 * eine Angabe hinzu: die gerundete Zahl der Anfragen je Woche. Zahlen eines
 * einzelnen Betreibers, Preise oder Leads bleiben aussen vor.
 */
class IndustryStatsService
{
    /**
     * Der Schluessel des gemerkten Durchschnitts. Steht so im Ticket und wird
     * von der Admin-Ansicht zum Verwerfen gebraucht.
     */
    public const CACHE_KEY = 'industry_weekly_avg';

    /**
     * Der Schluessel der gemerkten Leadpreise je Funnel. Eigener Schluessel,
     * weil die Preise mit den Anfragezahlen nichts zu tun haben -- und weil
     * sie das Portal nie verlassen (siehe averageLeadPriceCents).
     */
    public const PRICE_CACHE_KEY = 'industry_lead_prices';

    /**
     * Die Branchen fuer die Kachelauswahl, nach Anfragevolumen absteigend und
     * bei gleichem Volumen alphabetisch.
     *
     * Portale ohne veroeffentlichten Funnel stehen nicht in der Liste: Sie
     * koennten keine Anfrage liefern, und eine gewaehlte Branche ohne Angebot
     * waere ein leerer Marktplatz.
     *
     * @return list<array{funnel_id: int, name: string, icon: string, weekly: int}>
     */
    public function industries(): array
    {
        $weekly = $this->weeklyAverages();
        $industries = [];

        foreach (app(MarketplaceCatalog::class)->publishedFunnels() as $funnelId => $name) {
            $industries[] = [
                'funnel_id' => (int) $funnelId,
                'name' => $name,
                'icon' => $this->iconFor($name),
                'weekly' => $weekly[(int) $funnelId] ?? 0,
            ];
        }

        usort(
            $industries,
            static fn (array $left, array $right): int => [$right['weekly'], $left['name']] <=> [$left['weekly'], $right['name']],
        );

        return $industries;
    }

    /**
     * Die gerundete Zahl der Anfragen je Woche und Funnel. 0 heisst: unterhalb
     * der Mindestzahl, also "neu".
     *
     * @return array<int, int>
     */
    public function weeklyAverages(): array
    {
        /** @var array<int, int> $averages */
        $averages = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes((int) config('funnel.onboarding.industry_stats.cache_minutes')),
            fn (): array => $this->computeWeeklyAverages(),
        );

        return $averages;
    }

    /**
     * Wirft die gemerkte Auswertung weg.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::PRICE_CACHE_KEY);
    }

    /**
     * Der mittlere Leadpreis (Cent) der gewaehlten Branchen -- die Grundlage
     * fuer "reicht fuer ca. n Leads" im Zahlungsschritt (LP-ONBOARD-010).
     *
     * Ohne Auswahl oder ohne bekannte Preise kommt der Vorgabepreis aus
     * config('wallet.default_lead_price_cents') heraus. Das ist dieselbe Zahl,
     * mit der die Aufladeseite rechnet (LP-PORTAL-014), und damit nennen beide
     * Seiten denselben Gegenwert.
     *
     * **Was hier NICHT passiert:** einen Preis je Branche nach draussen geben.
     * Zurueck kommt eine einzige gemittelte Zahl; die Preistabelle bleibt in
     * dieser Klasse. Der Preis gehoert dem Verkaeufer, und die Grenze des
     * Freigegebenen zieht MarketplaceCatalog ausdruecklich ohne Preise.
     *
     * Eine Orientierung und keine Zusage: Jeder Verkaeufer setzt seinen Preis
     * selbst, und berechnet wird am Ende der Preis des gekauften Leads.
     *
     * @param  list<int>  $funnelIds
     */
    public function averageLeadPriceCents(array $funnelIds): int
    {
        $default = max(1, (int) config('wallet.default_lead_price_cents'));

        if ($funnelIds === []) {
            return $default;
        }

        $prices = $this->leadPrices();
        $selected = [];

        foreach ($funnelIds as $funnelId) {
            $price = $prices[(int) $funnelId] ?? null;

            if ($price !== null && $price > 0) {
                $selected[] = $price;
            }
        }

        if ($selected === []) {
            return $default;
        }

        return max(1, (int) round(array_sum($selected) / count($selected)));
    }

    /**
     * Leadpreis (Cent) je veroeffentlichtem Funnel, eine Stunde gemerkt.
     *
     * Der Preis haengt am Verkaeufer-Mandanten und nicht am Funnel
     * (`tenants.lead_price_cents`, so liest ihn auch PurchaseLead) -- zwei
     * Funnels desselben Betreibers tragen deshalb denselben Preis.
     *
     * @return array<int, int>
     */
    private function leadPrices(): array
    {
        /** @var array<int, int> $prices */
        $prices = Cache::remember(
            self::PRICE_CACHE_KEY,
            now()->addMinutes((int) config('funnel.onboarding.industry_stats.cache_minutes')),
            static fn (): array => Funnel::query()
                // Wie oben: Die Funnels gehoeren den Betreibern, gelesen wird
                // im Kontext des Kaeufers.
                ->withoutGlobalScopes(TenantScopes::names())
                ->published()
                ->join('tenants', 'tenants.id', '=', 'funnels.tenant_id')
                ->pluck('tenants.lead_price_cents', 'funnels.id')
                ->map(static fn (mixed $cents): int => (int) $cents)
                ->all(),
        );

        return $prices;
    }

    /**
     * Die Summe der Auswahl -- die Zahl in der Live-Zeile unter dem Raster.
     *
     * Aufaddiert werden die gerundeten Werte und nicht die Rohzahlen: Sonst
     * nennt die Zeile eine andere Summe als die Kacheln darueber, aus denen
     * der Kaeufer sie zusammenrechnet.
     *
     * @param  list<int>  $funnelIds
     */
    public function weeklyTotalFor(array $funnelIds): int
    {
        $weekly = $this->weeklyAverages();
        $total = 0;

        foreach ($funnelIds as $funnelId) {
            $total += $weekly[(int) $funnelId] ?? 0;
        }

        return $total;
    }

    /**
     * Das Symbol einer Branche, aus dem Portalnamen abgeleitet
     * (config('funnel.onboarding.industry_icons')).
     */
    public function iconFor(string $name): string
    {
        $haystack = mb_strtolower($name);

        /** @var array<string, string> $icons */
        $icons = config('funnel.onboarding.industry_icons', []);

        foreach ($icons as $needle => $icon) {
            if (str_contains($haystack, mb_strtolower((string) $needle))) {
                return (string) $icon;
            }
        }

        return (string) config('funnel.onboarding.industry_icon_default', 'leads');
    }

    /**
     * Eine Abfrage ueber alle Betreiber: Anfragen je Funnel im Zeitfenster.
     *
     * Gezaehlt wird, was im Marktplatz angekommen ist -- nicht jeder Datensatz
     * in der Tabelle. Ein noch nicht freigegebener (`neu`) und ein als
     * ungueltig verworfener Lead stand nie zum Kauf, und ein Duplikat zaehlt
     * einmal. Was danach aus dem Lead wurde (verkauft, abgelaufen), ist fuer
     * die Frage "wie viel kommt hier an?" ohne Belang.
     *
     * @return array<int, int>
     */
    private function computeWeeklyAverages(): array
    {
        $funnelIds = array_keys(app(MarketplaceCatalog::class)->publishedFunnels());

        if ($funnelIds === []) {
            return [];
        }

        $windowDays = max(1, (int) config('funnel.onboarding.industry_stats.window_days'));

        $counts = Lead::query()
            // Die Leads gehoeren den Betreibern, gelesen wird im Kontext des
            // Kaeufers -- ohne das Abschalten des Scopes waere jede Zahl null.
            ->withoutGlobalScopes(TenantScopes::names())
            ->whereIn('funnel_id', $funnelIds)
            ->whereNotIn('lead_state', [LeadState::NEU->value, LeadState::UNGUELTIG->value])
            ->whereNull('duplicate_of_lead_id')
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->groupBy('funnel_id')
            ->selectRaw('funnel_id, count(*) as leads')
            ->pluck('leads', 'funnel_id');

        $weeks = $windowDays / 7;
        $averages = [];

        foreach ($counts as $funnelId => $leads) {
            $averages[(int) $funnelId] = $this->round(((int) $leads) / $weeks);
        }

        return $averages;
    }

    /**
     * Rundet auf das konfigurierte Vielfache. Unterhalb der Mindestzahl
     * kommt 0 heraus -- das Zeichen fuer "neu".
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
