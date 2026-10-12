<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Die Umkreisstufen der Kaufkriterien (LP-ONBOARD-009, LP-PORTAL-011).
 *
 * **Feste Stufen statt jedes Kilometers.** "47 Kilometer" ist keine
 * Entscheidung, die jemand trifft, und jeder Zwischenwert waere eine weitere
 * Abfrage fuer eine Zahl, die sich nicht aendert.
 *
 * **Eine Quelle fuer drei Oberflaechen.** Wizard-Schritt 2, die Kaufkriterien
 * im Portal und das Filament-Formular bieten denselben Umkreis an. Lagen die
 * Stufen in jeder Oberflaeche einzeln, speicherte eine einen Wert, den die
 * naechste auf die nachbarliche Stufe rundet -- und der Kaeufer saehe nach dem
 * Oeffnen einer anderen Seite einen anderen Umkreis als den, den er gesetzt
 * hat.
 */
final class BuyerRadius
{
    /**
     * Die angebotenen Umkreise in Kilometern, aufsteigend.
     *
     * @var list<int>
     */
    public const STEPS = [10, 25, 50, 100, 200];

    /** Der vorgewaehlte Umkreis in Kilometern, wenn der Kaeufer einschraenkt. */
    public const DEFAULT_KM = 50;

    /**
     * Die vorgewaehlte Reglerstufe -- die Stelle von DEFAULT_KM in STEPS.
     *
     * Als Konstante und nicht als Methode, weil Livewire-Komponenten sie als
     * Vorgabe einer Eigenschaft brauchen und PHP dort keinen Aufruf erlaubt.
     * Wer STEPS aendert, zaehlt sie hier mit nach.
     */
    public const DEFAULT_INDEX = 2;

    /**
     * @return list<int>
     */
    public static function steps(): array
    {
        return self::STEPS;
    }

    /**
     * Die Stufen als Auswahlliste: Kilometer => "50 km".
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::steps() as $step) {
            $options[$step] = $step.' '.__('marketplace.profile.portal.region.radius.unit');
        }

        return $options;
    }

    /**
     * Die Stufe, die einem gespeicherten Umkreis am naechsten liegt.
     *
     * Ein Wert ausserhalb der Stufen ist keine Fehlermeldung wert -- er landet
     * auf der nachbarlichen Stufe.
     */
    public static function indexFor(int $radiusKm): int
    {
        $steps = self::steps();
        $closest = 0;

        foreach ($steps as $index => $step) {
            if (abs($step - $radiusKm) < abs($steps[$closest] - $radiusKm)) {
                $closest = $index;
            }
        }

        return $closest;
    }

    /**
     * Die Kilometer zu einer Reglerstufe. Der Index kommt aus dem Browser und
     * wird hier eingefangen.
     */
    public static function kmForIndex(int $index): int
    {
        $steps = self::steps();

        return $steps[max(0, min(count($steps) - 1, $index))];
    }
}
