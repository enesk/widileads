<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Funnel\Theme\ContrastRatio;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FB-027: Die Kontrastberechnung nach WCAG 2.1.
 *
 * Die einzige Stelle der Barrierefreiheit, die sich sinnvoll mit PHPUnit
 * pruefen laesst - eine reine Funktion mit bekannten Referenzwerten. Sie traegt
 * die Warnung im Theme-Editor: rechnet sie falsch, warnt der Editor entweder
 * nie oder staendig, und beides waere schlimmer als keine Warnung.
 */
class ContrastRatioTest extends TestCase
{
    /**
     * Referenzwerte aus WCAG 2.1. Die beiden Grauwerte sind der klassische
     * Grenzfall: #767676 auf Weiss besteht AA knapp, #777777 nicht mehr.
     *
     * @return array<string, array{string, string, float}>
     */
    public static function ratioProvider(): array
    {
        return [
            'Schwarz auf Weiss ist das Maximum' => ['#000000', '#ffffff', 21.0],
            'Weiss auf Weiss ist das Minimum' => ['#ffffff', '#ffffff', 1.0],
            'Schwarz auf Schwarz ist das Minimum' => ['#000000', '#000000', 1.0],
            'Grenzfall bestanden' => ['#767676', '#ffffff', 4.54],
            'Grenzfall knapp verfehlt' => ['#777777', '#ffffff', 4.48],
            'Standard-Primaerfarbe auf Weiss' => ['#2563eb', '#ffffff', 5.17],
            'Reines Rot auf Weiss' => ['#ff0000', '#ffffff', 4.0],
            'Reines Blau auf Weiss' => ['#0000ff', '#ffffff', 8.59],
        ];
    }

    #[DataProvider('ratioProvider')]
    public function test_the_ratio_matches_the_wcag_reference(string $first, string $second, float $expected): void
    {
        $this->assertSame($expected, ContrastRatio::between($first, $second));
    }

    #[DataProvider('ratioProvider')]
    public function test_the_ratio_is_the_same_in_both_directions(string $first, string $second, float $expected): void
    {
        // Das Verhaeltnis kennt kein Vorne und Hinten: Der hellere Wert steht
        // immer im Zaehler. Ohne das wuerde die Warnung davon abhaengen,
        // welches Feld der Betreiber zuerst geaendert hat.
        $this->assertSame(
            ContrastRatio::between($first, $second),
            ContrastRatio::between($second, $first),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function equivalentNotationProvider(): array
    {
        return [
            'mit und ohne Raute' => ['#2563eb', '2563eb'],
            'Kurzform und Langform' => ['#fff', '#ffffff'],
            'Gross- und Kleinschreibung' => ['#2563EB', '#2563eb'],
            'mit Leerzeichen' => ['  #2563eb  ', '#2563eb'],
        ];
    }

    #[DataProvider('equivalentNotationProvider')]
    public function test_equivalent_notations_give_the_same_ratio(string $first, string $second): void
    {
        $this->assertSame(
            ContrastRatio::between($second, '#ffffff'),
            ContrastRatio::between($first, '#ffffff'),
        );
    }

    public function test_an_unreadable_value_counts_as_black(): void
    {
        // Die Farbe kommt aus einem Formularfeld. Eine Ausnahme waere hier
        // unangemessen; Schwarz ist die sichere Annahme, weil sie den Kontrast
        // nicht schoenrechnet.
        $this->assertSame(21.0, ContrastRatio::between('kein Hex', '#ffffff'));
    }

    public function test_the_aa_thresholds_are_applied(): void
    {
        $this->assertTrue(ContrastRatio::meetsNormalText('#767676', '#ffffff'));
        $this->assertFalse(ContrastRatio::meetsNormalText('#777777', '#ffffff'));

        // Grosser Text und Bedienelemente duerfen bei 3.0 liegen - beide
        // Grauwerte bestehen dort.
        $this->assertTrue(ContrastRatio::meetsLargeText('#777777', '#ffffff'));
        $this->assertTrue(ContrastRatio::meetsNonText('#777777', '#ffffff'));

        $this->assertFalse(ContrastRatio::meetsLargeText('#bbbbbb', '#ffffff'));
    }
}
