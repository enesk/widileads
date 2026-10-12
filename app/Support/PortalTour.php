<?php

declare(strict_types=1);

namespace App\Support;

use App\Constants\OnboardingStep;

/**
 * Die gefuehrten Touren des Portals (LP-ONBOARD-013).
 *
 * Zwei Touren, und beide haengen an drei Dingen: einem Namen, einem Schritt in
 * `tenant_onboarding_steps` und der Portalseite, auf der sie laeuft. Die
 * Zuordnung steht hier und nicht in der Routendatei, im Layout oder im Skript,
 * weil vier Stellen sie brauchen: der Rahmen (zeigt die Tour an), die Route
 * (schraenkt ihren Parameter ein), der Controller (haelt den Namen fuer gueltig)
 * und der Zuhoerer (hakt den passenden Schritt ab). Liefen sie auseinander,
 * meldete eine abgeschlossene Tour einen Schritt, den niemand liest -- und sie
 * startete beim naechsten Besuch wieder.
 *
 * Die Namen stehen so in den data-tour-Attributen des Markups und in der
 * Adresse (?tour=marketplace) und duerfen nach dem ersten Einsatz nicht mehr
 * geaendert werden.
 */
final class PortalTour
{
    public const MARKETPLACE = 'marketplace';

    public const LEAD_DETAIL = 'lead_detail';

    /**
     * Die Routennamen, auf denen eine Tour laeuft.
     *
     * Kein Verifizierungs-Screen und kein Wizard-Schritt: Dort hat der Kaeufer
     * genau eine Aufgabe, und die erklaert sich selbst.
     *
     * @var array<string, string>
     */
    private const ROUTES = [
        'portal.marketplace' => self::MARKETPLACE,
        'portal.leads.show' => self::LEAD_DETAIL,
    ];

    /**
     * So viele data-tour-Marken muss die Seite tragen, damit die Tour startet.
     *
     * Der Vertrag zwischen Markup und Skript: Fehlt eine Marke -- auf einem
     * leeren Marktplatz gibt es keine Lead-Karte und damit auch keinen
     * Kaufknopf --, startet die Tour stillschweigend nicht und wird beim
     * naechsten Besuch erneut versucht. Ohne diese Zahl koennte das Skript eine
     * fehlende Marke nicht von einer absichtlich kurzen Tour unterscheiden und
     * wuerde zwei Schritte zeigen, die nichts erklaeren.
     *
     * Wer einen Schritt im Markup ergaenzt oder entfernt, zaehlt hier mit.
     *
     * @var array<string, int>
     */
    private const MARKERS = [
        self::MARKETPLACE => 4,
        self::LEAD_DETAIL => 4,
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return [self::MARKETPLACE, self::LEAD_DETAIL];
    }

    /**
     * Die Tour dieser Seite -- oder null, wenn die Seite keine hat.
     */
    public static function forRouteName(?string $routeName): ?string
    {
        return $routeName === null ? null : (self::ROUTES[$routeName] ?? null);
    }

    /**
     * Die Zahl der Marken, die diese Tour im Markup erwartet.
     */
    public static function markerCount(string $tour): int
    {
        return self::MARKERS[$tour] ?? 0;
    }

    /**
     * Der Onboarding-Schritt, der diese Tour abhakt. Null bei unbekanntem Namen.
     */
    public static function step(string $tour): ?OnboardingStep
    {
        return match ($tour) {
            self::MARKETPLACE => OnboardingStep::TOUR_MARKETPLACE_SEEN,
            self::LEAD_DETAIL => OnboardingStep::TOUR_LEAD_DETAIL_SEEN,
            default => null,
        };
    }
}
