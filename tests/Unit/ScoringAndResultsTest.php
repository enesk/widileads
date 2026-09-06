<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Funnel\Results\ResultRangeValidator;
use App\Funnel\Results\ResultResolver;
use App\Funnel\Scoring\ScoreCalculator;
use App\Funnel\Snapshots\FunnelSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FB-013: Scoring und Ergebnis-Screens.
 *
 * Die Punktzahl entscheidet, welches Ergebnis der Endkunde sieht -- und damit,
 * als wie wertvoll der Lead spaeter gilt. Ein Rechenfehler faellt niemandem auf,
 * er landet nur im falschen Ergebnis. Deshalb datengetrieben abgesichert, ohne
 * Datenbank.
 */
class ScoringAndResultsTest extends TestCase
{
    /**
     * Funnel wie der Pfotencheck: Tierart (Einfachauswahl) und Vorerkrankungen
     * (Mehrfachauswahl), dazu drei Ergebnisbereiche.
     *
     * @param  list<array<string, mixed>>|null  $results
     */
    private function snapshot(?array $results = null): FunnelSnapshot
    {
        return FunnelSnapshot::fromArray([
            'funnel' => ['public_token' => '01J8ZTESTTOKEN', 'name' => 'Pfotencheck'],
            'steps' => [
                [
                    'position' => 1,
                    'title' => 'Dein Tier',
                    'questions' => [
                        [
                            'field_key' => 'tierart',
                            'type' => 'single_choice',
                            'position' => 1,
                            'options' => [
                                ['value' => 'hund', 'label' => 'Hund', 'score' => 3, 'position' => 1],
                                ['value' => 'katze', 'label' => 'Katze', 'score' => 2, 'position' => 2],
                                ['value' => 'anderes', 'label' => 'Anderes Tier', 'score' => null, 'position' => 3],
                            ],
                        ],
                        [
                            'field_key' => 'vorerkrankungen',
                            'type' => 'multi_choice',
                            'position' => 2,
                            'options' => [
                                ['value' => 'huefte', 'label' => 'Huefte', 'score' => 4, 'position' => 1],
                                ['value' => 'augen', 'label' => 'Augen', 'score' => 2, 'position' => 2],
                                ['value' => 'keine', 'label' => 'Keine', 'score' => 0, 'position' => 3],
                            ],
                        ],
                        // Freitext ohne Optionen -- traegt nichts zur Punktzahl bei.
                        ['field_key' => 'anmerkung', 'type' => 'text', 'position' => 3],
                    ],
                ],
            ],
            'results' => $results ?? [
                ['min_score' => 0, 'max_score' => 3, 'title' => 'Geringes Risiko'],
                ['min_score' => 4, 'max_score' => 7, 'title' => 'Erhoehtes Risiko'],
                ['min_score' => 8, 'max_score' => 9, 'title' => 'Hohes Risiko'],
            ],
        ]);
    }

    /**
     * @return array<string, array{array<string, mixed>, int}>
     */
    public static function scoreProvider(): array
    {
        return [
            'ohne Antworten' => [[], 0],
            'Einfachauswahl' => [['tierart' => 'hund'], 3],
            'Option ohne Punktwert' => [['tierart' => 'anderes'], 0],
            'Mehrfachauswahl summiert' => [['vorerkrankungen' => ['huefte', 'augen']], 6],
            'Mehrfachauswahl mit Nullwert' => [['vorerkrankungen' => ['keine']], 0],
            'beide Fragen zusammen' => [['tierart' => 'hund', 'vorerkrankungen' => ['huefte', 'augen']], 9],
            'Freitext zaehlt nicht' => [['tierart' => 'katze', 'anmerkung' => 'Hallo'], 2],
            'unbekannte Option zaehlt nicht' => [['tierart' => 'pferd'], 0],
            'Gross-/Kleinschreibung egal' => [['tierart' => 'Hund'], 3],
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    #[DataProvider('scoreProvider')]
    public function test_score_is_summed_over_the_chosen_options(array $answers, int $expected): void
    {
        $this->assertSame($expected, app(ScoreCalculator::class)->calculate($this->snapshot(), $answers));
    }

    /**
     * @return array<string, array{int, ?string}>
     */
    public static function resultProvider(): array
    {
        return [
            'Untergrenze des ersten Bereichs' => [0, 'Geringes Risiko'],
            'Obergrenze des ersten Bereichs' => [3, 'Geringes Risiko'],
            'Untergrenze des zweiten Bereichs' => [4, 'Erhoehtes Risiko'],
            'Obergrenze des zweiten Bereichs' => [7, 'Erhoehtes Risiko'],
            'Untergrenze des dritten Bereichs' => [8, 'Hohes Risiko'],
            'ausserhalb aller Bereiche' => [42, null],
        ];
    }

    #[DataProvider('resultProvider')]
    public function test_the_score_picks_the_matching_result(int $score, ?string $expectedTitle): void
    {
        $result = app(ResultResolver::class)->resolve($this->snapshot(), $score);

        $this->assertSame($expectedTitle, $result?->title);
    }

    public function test_answers_lead_straight_to_their_result(): void
    {
        $result = app(ResultResolver::class)->resolveForAnswers($this->snapshot(), [
            'tierart' => 'hund',
            'vorerkrankungen' => ['huefte', 'augen'],
        ]);

        $this->assertSame('Hohes Risiko', $result?->title);
        $this->assertTrue($result?->showContactForm);
    }

    /**
     * @return array<string, array{list<array<string, mixed>>, bool, ?string}>
     */
    public static function rangeProvider(): array
    {
        return [
            'lueckenlos und ueberschneidungsfrei' => [
                [
                    ['min_score' => 0, 'max_score' => 3, 'title' => 'Gering'],
                    ['min_score' => 4, 'max_score' => 7, 'title' => 'Erhoeht'],
                    ['min_score' => 8, 'max_score' => 9, 'title' => 'Hoch'],
                ],
                true,
                null,
            ],
            'Luecke zwischen zwei Bereichen' => [
                [
                    ['min_score' => 0, 'max_score' => 3, 'title' => 'Gering'],
                    ['min_score' => 6, 'max_score' => 9, 'title' => 'Hoch'],
                ],
                false,
                'Punktzahlen 4 bis 5',
            ],
            'Luecke am Anfang' => [
                [
                    ['min_score' => 4, 'max_score' => 9, 'title' => 'Erhoeht'],
                ],
                false,
                'Punktzahlen 0 bis 3',
            ],
            'Luecke am Ende bis zur hoechsten erreichbaren Punktzahl' => [
                [
                    ['min_score' => 0, 'max_score' => 5, 'title' => 'Gering'],
                ],
                false,
                'Punktzahlen 6 bis 9',
            ],
            'Ueberschneidung' => [
                [
                    ['min_score' => 0, 'max_score' => 5, 'title' => 'Gering'],
                    ['min_score' => 4, 'max_score' => 9, 'title' => 'Hoch'],
                ],
                false,
                'ueberschneiden sich',
            ],
            'vertauschte Grenzen' => [
                [
                    ['min_score' => 0, 'max_score' => 9, 'title' => 'Alles'],
                    ['min_score' => 7, 'max_score' => 2, 'title' => 'Verdreht'],
                ],
                false,
                'ungueltig',
            ],
            'ohne Ergebnisse ist zulaessig' => [[], true, null],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    #[DataProvider('rangeProvider')]
    public function test_result_ranges_are_checked_for_gaps_and_overlaps(
        array $results,
        bool $shouldBeValid,
        ?string $expectedMessagePart,
    ): void {
        $report = app(ResultRangeValidator::class)->validate($this->snapshot($results));

        $this->assertSame($shouldBeValid, $report->isValid(), implode(' | ', $report->messages()));

        if ($expectedMessagePart !== null) {
            $this->assertStringContainsString($expectedMessagePart, implode(' | ', $report->messages()));
        }
    }

    public function test_the_highest_reachable_score_covers_multi_choice_sums(): void
    {
        // Tierart hoechstens 3, Vorerkrankungen 4 + 2 + 0 = 6 -> zusammen 9.
        $this->assertSame(9, app(ScoreCalculator::class)->maximumScore($this->snapshot()));
    }
}
