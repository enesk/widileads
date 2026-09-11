<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Die Navigation des Portal-Arbeitsbereichs (Portal Phase 1).
 *
 * Der Entwurf zeigt dieselbe Navigation zweimal: in der Schublade fuer schmale
 * Bildschirme und in der festen Spalte daneben. Beide lesen hier, damit ein
 * neuer Punkt nicht an einer der beiden Stellen vergessen wird.
 *
 * Die Routennamen sind die Namen aus routes/web.php, englisch und in
 * Kleinschreibung mit Bindestrich (portal.marketplace, portal.buying-criteria).
 * Die Pfade bleiben deutsch, die Namen nicht -- eine zweite Schreibweise hier
 * haette Route::has() still false liefern lassen und den Punkt ohne Ziel
 * zurueckgelassen (Ticket #7).
 *
 * Jede Portalroute traegt {tenant:uuid} im Pfad, der Mandant gehoert beim
 * Aufloesen also immer mitgegeben, sonst wirft route() eine
 * UrlGenerationException und reisst die ganze Seite mit.
 */
final class PortalNavigation
{
    /**
     * Die Navigation eines Workspaces, in der Reihenfolge des Entwurfs.
     *
     * Die Verkaeuferpunkte (Funnels, Einnahmen, Auszahlung, Berichte) haben
     * bereits Routen, stehen im Entwurf aber nicht in der Navigation. Sie
     * kommen in Phase 3 des Epics dazu.
     *
     * @return list<array{label: ?string, items: list<array{label: string, icon: string, route: string, url: ?string}>}>
     */
    public static function forTenant(Tenant $tenant): array
    {
        return self::resolve($tenant, [
            [
                'label' => null,
                'items' => [
                    ['label' => __('portal.nav.dashboard'), 'icon' => 'home', 'route' => 'portal.overview'],
                    ['label' => __('portal.nav.my_leads'), 'icon' => 'leads', 'route' => 'portal.leads'],
                ],
            ],
            [
                'label' => __('portal.nav.group.marketplace'),
                'items' => [
                    ['label' => __('portal.nav.marketplace'), 'icon' => 'cart', 'route' => 'portal.marketplace'],
                    ['label' => __('portal.nav.buying_criteria'), 'icon' => 'sliders', 'route' => 'portal.buying-criteria'],
                    ['label' => __('portal.nav.caller_id'), 'icon' => 'phone', 'route' => 'portal.caller-id'],
                    ['label' => __('portal.nav.balance'), 'icon' => 'wallet', 'route' => 'portal.wallet'],
                ],
            ],
            [
                'label' => __('portal.nav.group.billing'),
                'items' => [
                    ['label' => __('portal.nav.orders'), 'icon' => 'package', 'route' => 'portal.orders'],
                    ['label' => __('portal.nav.payments'), 'icon' => 'card', 'route' => 'portal.transactions'],
                ],
            ],
        ]);
    }

    /**
     * Dieselbe Seite im anderen Workspace (Ticket #6).
     *
     * Der Wechsel bleibt ein gewoehnlicher Verweis: Der Mandantenkontext steht
     * im Pfad und nirgends sonst (siehe Kopf von routes/web.php), also reicht
     * dieselbe Route mit der anderen UUID. Eine Umschaltung in der Session
     * wuerde einen zweiten geoeffneten Workspace mitreissen.
     *
     * Zwei Faelle landen auf der Uebersicht statt auf derselben Seite: eine
     * Adresse ausserhalb des Portals, und eine Adresse mit einem weiteren
     * Parameter (etwa ein Lead-Detail) -- dieser Datensatz gehoert dem alten
     * Workspace und waere im neuen ein Fremdzugriff.
     */
    public static function switchUrl(Tenant $target): string
    {
        $overview = route('portal.overview', ['tenant' => $target->uuid]);
        $current = Route::current();
        $name = $current?->getName();

        if ($name === null || ! str_starts_with($name, 'portal.') || $name === 'portal.home') {
            return $overview;
        }

        $parameters = $current->parameters();
        unset($parameters['tenant']);

        if ($parameters !== []) {
            return $overview;
        }

        return route($name, ['tenant' => $target->uuid]);
    }

    /**
     * Ergaenzt jeden Punkt um seine Adresse -- null, wenn sie nicht baubar ist.
     *
     * Ein Punkt ohne Ziel bleibt sichtbar, statt die ganze Seite mitzureissen.
     * Er wird aber protokolliert: Genau dieser stille Ausfall hat den
     * Marktplatz unbemerkt unerreichbar gemacht (Ticket #7).
     *
     * @param  list<array{label: ?string, items: list<array{label: string, icon: string, route: string}>}>  $sections
     * @return list<array{label: ?string, items: list<array{label: string, icon: string, route: string, url: ?string}>}>
     */
    private static function resolve(Tenant $tenant, array $sections): array
    {
        return array_map(static function (array $section) use ($tenant): array {
            $section['items'] = array_map(
                static fn (array $item): array => $item + ['url' => self::url($item['route'], $tenant)],
                $section['items'],
            );

            return $section;
        }, $sections);
    }

    /**
     * Die Adresse eines Navigationspunktes, oder null samt Protokolleintrag.
     */
    private static function url(string $name, Tenant $tenant): ?string
    {
        if (! Route::has($name)) {
            Log::warning('Portalnavigation: Routenname unbekannt.', ['route' => $name]);

            return null;
        }

        try {
            return route($name, ['tenant' => $tenant->uuid]);
        } catch (UrlGenerationException $exception) {
            Log::warning('Portalnavigation: Adresse nicht baubar.', [
                'route' => $name,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
