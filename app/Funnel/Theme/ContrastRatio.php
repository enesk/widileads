<?php

declare(strict_types=1);

namespace App\Funnel\Theme;

/**
 * Kontrastverhaeltnis zweier Farben nach WCAG 2.1 (FB-027).
 *
 * Der Betreiber waehlt die Farben seines Funnels selbst und kann den Kontrast
 * dabei zerstoeren, ohne es zu merken - auf seinem Bildschirm sieht Hellgrau auf
 * Weiss oft noch lesbar aus. Die Rechnung hier ist die Grundlage fuer die
 * Warnung im Theme-Editor.
 *
 * Formel: (L1 + 0.05) / (L2 + 0.05), wobei L die relative Leuchtdichte ist und
 * L1 die hellere der beiden Farben. Ergebnis zwischen 1 (identisch) und 21
 * (Schwarz auf Weiss).
 */
class ContrastRatio
{
    /** Mindestverhaeltnis fuer normalen Text nach WCAG 2.1 AA. */
    public const AA_NORMAL_TEXT = 4.5;

    /** Mindestverhaeltnis fuer grossen Text (ab 18pt bzw. 14pt fett). */
    public const AA_LARGE_TEXT = 3.0;

    /** Mindestverhaeltnis fuer Bedienelemente und grafische Objekte. */
    public const AA_NON_TEXT = 3.0;

    /**
     * Kontrastverhaeltnis zweier Hex-Farben, auf zwei Stellen gerundet.
     */
    public static function between(string $first, string $second): float
    {
        $lighter = max(self::relativeLuminance($first), self::relativeLuminance($second));
        $darker = min(self::relativeLuminance($first), self::relativeLuminance($second));

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    public static function meetsNormalText(string $foreground, string $background): bool
    {
        return self::between($foreground, $background) >= self::AA_NORMAL_TEXT;
    }

    public static function meetsLargeText(string $foreground, string $background): bool
    {
        return self::between($foreground, $background) >= self::AA_LARGE_TEXT;
    }

    public static function meetsNonText(string $foreground, string $background): bool
    {
        return self::between($foreground, $background) >= self::AA_NON_TEXT;
    }

    /**
     * Relative Leuchtdichte nach WCAG 2.1.
     *
     * Die Kanaele werden erst linearisiert (die sRGB-Werte sind
     * gammakorrigiert) und dann nach der Empfindlichkeit des Auges gewichtet -
     * Gruen traegt am meisten bei, Blau am wenigsten.
     */
    public static function relativeLuminance(string $hex): float
    {
        [$red, $green, $blue] = self::channels($hex);

        return 0.2126 * self::linearize($red)
            + 0.7152 * self::linearize($green)
            + 0.0722 * self::linearize($blue);
    }

    /**
     * Die drei Kanaele als Werte zwischen 0 und 1.
     *
     * Nimmt "#RRGGBB" und "RRGGBB", ebenso die Kurzform "#RGB". Was sich nicht
     * lesen laesst, gilt als Schwarz - eine Ausnahme waere hier unangemessen,
     * die Farbe kommt aus einem Formularfeld.
     *
     * @return array{float, float, float}
     */
    private static function channels(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex) === 1) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return [0.0, 0.0, 0.0];
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    private static function linearize(float $channel): float
    {
        return $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
