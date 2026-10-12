<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Onboarding\OnboardingService;
use App\Support\PortalCallerIdLock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Haelt einen Kaeufer im Einrichtungs-Wizard, bis er ihn abgeschlossen oder
 * ausdruecklich uebersprungen hat (LP-ONBOARD-007).
 *
 * Der Unterschied zur Telefonsperre daneben: Das hier ist keine
 * Zugriffskontrolle, sondern eine Fuehrung. Ein Kaeufer ohne Branchen, Region
 * und Zahlungsweise sieht auf dem Marktplatz nichts, was zu ihm passt -- er
 * wuerde eine leere Liste fuer ein leeres Angebot halten und wieder gehen.
 * Deshalb Umleitung und nicht 403: Das Ziel ist der naechste Schritt und keine
 * Sackgasse.
 *
 * **Nur Kaeufer-Workspaces.** Ein Betreiber- oder Verkaeufer-Mandant hat
 * weder Branchen noch Guthaben; fuer ihn ist der Wizard gegenstandslos. Die
 * Telefonsperre gilt dagegen fuer alle -- eine Regel fuer alle ist eine Regel,
 * die man beim Lesen nicht uebersieht; diese hier ist bewusst die Ausnahme.
 *
 * **Reihenfolge:** hinter `portal.tenant` -- vorher steht der Mandant nicht und
 * ohne ihn ist nichts zu entscheiden. Festgelegt ist sie durch die Stelle im
 * Middleware-Array der Portalgruppe; ein Eintrag in der Prioritaetsliste ist
 * nicht noetig, weil `portal.tenant` dort steht und damit ohnehin vorher laeuft.
 *
 * **Die Rufnummernbestaetigung bleibt frei:** Ein Kaeufer soll die Seite aus
 * dem Dashboard-Hinweis heraus ansteuern koennen, auch waehrend der Wizard noch
 * offen ist. Die Rufnummernanzeige ist Voraussetzung fuer den Anruf -- wer sie
 * regeln will, soll nicht auf den Abschluss des Wizards warten muessen.
 *
 * **Nicht als persistente Livewire-Middleware eingetragen** -- anders als die
 * Telefonsperre. Folgeanfragen an /livewire/update laufen also ungeprueft
 * durch. Das ist hier richtig: Die Sperre schuetzt keine Daten, sie weist den
 * Weg. Eingetragen muesste sie ihre Antwort fuer fetch() als JSON mit Grund
 * schicken und resources/js/modules/auth-guard.js einen zweiten Grund lernen
 * -- Aufwand und ein zweites Umleitungsrisiko fuer einen Nutzer, der die
 * Seite ohnehin nur ueber einen alten Tab offen hat.
 */
class EnsureOnboardingWizard
{
    /**
     * Routennamen, die trotz offenem Wizard erreichbar bleiben
     * (Str::is-Muster).
     */
    public const ALLOWED_ROUTE_NAMES = [
        // Der Wizard selbst samt moeglicher Unterschritte -- sonst leitete er
        // auf sich selbst um.
        'portal.onboarding',
        'portal.onboarding.*',

        // Abmelden muss immer gehen. Eine Fuehrung, aus der man nicht
        // herauskommt, ist eine Falle.
        'logout',

        // Konto loeschen bleibt moeglich (DSGVO). Die Route existiert noch
        // nicht; der Eintrag steht hier, damit sie nicht spaeter versehentlich
        // hinter dem Wizard landet.
        'account.delete',

        // Die eigene Rufnummer: erreichbar aus dem Hinweis im Dashboard, auch
        // solange der Wizard offen ist (siehe Klassenkommentar).
        PortalCallerIdLock::ALLOWED_ROUTE,
    ];

    public function __construct(private readonly OnboardingService $onboarding) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::isEnabled()) {
            return $next($request);
        }

        $tenant = $request->route('tenant');

        if (! $tenant instanceof Tenant || ! $tenant->isBuyer()) {
            return $next($request);
        }

        if (self::allowsRoute($request->route()?->getName())) {
            return $next($request);
        }

        $step = $this->onboarding->currentWizardStep($tenant);

        // Abgeschlossen oder uebersprungen: beides laeuft auf durchlassen
        // hinaus.
        if ($step === null || $step->wizardSlug() === null) {
            return $next($request);
        }

        return redirect()->route('portal.onboarding', [
            'tenant' => $tenant->uuid,
            'step' => $step->wizardSlug(),
        ]);
    }

    /**
     * Ist die Fuehrung eingeschaltet?
     *
     * Der Schalter ist fuer die manuelle Abnahme (LP-ONBOARD-016) und fuer
     * Umgebungen, in denen der Wizard im Weg steht -- nicht fuer den Betrieb.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('funnel.onboarding.wizard_gate', true);
    }

    /**
     * Darf dieser Routenname trotz offenem Wizard durch?
     */
    public static function allowsRoute(?string $routeName): bool
    {
        if ($routeName === null) {
            // Eine Route ohne Namen laesst sich nicht freischalten -- im
            // Zweifel fuehren.
            return false;
        }

        return Str::is(self::ALLOWED_ROUTE_NAMES, $routeName);
    }
}
