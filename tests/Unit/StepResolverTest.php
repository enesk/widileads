<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\FunnelStepCycleException;
use App\Funnel\Conditions\StepResolver;
use App\Funnel\Snapshots\FunnelSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FB-012: Verzweigungslogik.
 *
 * Ein falsch ausgewerteter Operator schickt den Endkunden in den falschen
 * Schritt -- und der Lead entsteht mit den falschen Angaben. Deshalb laufen alle
 * acht Operatoren als Datensatz durch, dazu Prioritaetsreihenfolge und
 * Zyklenschutz. Ohne Datenbank, weil der Resolver nur den Snapshot liest.
 */
class StepResolverTest extends TestCase
{
    private function resolver(): StepResolver
    {
        return app(StepResolver::class);
    }

    /**
     * Snapshot mit drei Schritten; die Quellfrage steht in Schritt 1.
     *
     * @param  list<array<string, mixed>>  $conditions
     */
    private function snapshot(array $conditions, int $stepCount = 3): FunnelSnapshot
    {
        $steps = [];

        for ($position = 1; $position <= $stepCount; $position++) {
            $steps[] = [
                'position' => $position,
                'title' => 'Schritt '.$position,
                'questions' => $position === 1
                    ? [['field_key' => 'tierart', 'type' => 'single_choice', 'position' => 1]]
                    : [['field_key' => 'feld_'.$position, 'type' => 'text', 'position' => 1]],
            ];
        }

        return FunnelSnapshot::fromArray([
            'funnel' => ['public_token' => '01J8ZTESTTOKEN', 'name' => 'Testfunnel'],
            'steps' => $steps,
            'conditions' => $conditions,
        ]);
    }

    /**
     * Je Operator ein treffender und ein nicht treffender Fall.
     *
     * @return array<string, array{string, mixed, mixed, int, bool}>
     */
    public static function operatorProvider(): array
    {
        return [
            'equals trifft' => ['equals', ['hund'], 'hund', 0, true],
            'equals trifft nicht' => ['equals', ['hund'], 'katze', 0, false],
            'equals ignoriert Gross-/Kleinschreibung' => ['equals', ['Hund'], 'hund', 0, true],
            'not_equals trifft' => ['not_equals', ['hund'], 'katze', 0, true],
            'not_equals trifft nicht' => ['not_equals', ['hund'], 'hund', 0, false],
            'in trifft' => ['in', ['hund', 'katze'], 'katze', 0, true],
            'in trifft nicht' => ['in', ['hund', 'katze'], 'pferd', 0, false],
            'gt trifft' => ['gt', 7, '8', 0, true],
            'gt trifft nicht' => ['gt', 7, '7', 0, false],
            'gt bei Text trifft nie' => ['gt', 7, 'sieben', 0, false],
            'lt trifft' => ['lt', 7, '6', 0, true],
            'lt trifft nicht' => ['lt', 7, '7', 0, false],
            'contains trifft als Teiltext' => ['contains', 'schaefer', 'Deutscher Schaeferhund', 0, true],
            'contains trifft nicht' => ['contains', 'schaefer', 'Dackel', 0, false],
            'contains trifft in Mehrfachauswahl' => ['contains', 'huefte', ['augen', 'huefte'], 0, true],
            'answered trifft' => ['answered', null, 'katze', 0, true],
            'answered trifft nicht bei leerer Antwort' => ['answered', null, '', 0, false],
            'answered trifft nicht ohne Antwort' => ['answered', null, null, 0, false],
            'score_gte trifft' => ['score_gte', 8, 'hund', 8, true],
            'score_gte trifft nicht' => ['score_gte', 8, 'hund', 7, false],
        ];
    }

    #[DataProvider('operatorProvider')]
    public function test_each_operator_decides_the_jump(
        string $operator,
        mixed $conditionValue,
        mixed $answer,
        int $score,
        bool $shouldJump,
    ): void {
        $snapshot = $this->snapshot([[
            'source_field_key' => 'tierart',
            'operator' => $operator,
            'value' => $conditionValue,
            'target_step_position' => 3,
            'priority' => 10,
        ]]);

        $next = $this->resolver()->next($snapshot, ['tierart' => $answer], 1, $score);

        // Trifft die Regel, wird Schritt 2 uebersprungen; sonst geht es der
        // Reihe nach weiter.
        $this->assertSame($shouldJump ? 3 : 2, $next);
    }

    public function test_the_highest_priority_wins_and_without_a_match_the_next_step_follows(): void
    {
        $snapshot = $this->snapshot([
            [
                'source_field_key' => 'tierart',
                'operator' => 'answered',
                'value' => null,
                'target_step_position' => 2,
                'priority' => 1,
            ],
            [
                'source_field_key' => 'tierart',
                'operator' => 'equals',
                'value' => ['hund'],
                'target_step_position' => 3,
                'priority' => 99,
            ],
        ], stepCount: 4);

        // Beide Regeln treffen zu -- die mit der hoeheren Prioritaet gewinnt.
        $this->assertSame(3, $this->resolver()->next($snapshot, ['tierart' => 'hund'], 1));

        // Die hoehere Regel trifft nicht mehr, die niedrigere schon.
        $this->assertSame(2, $this->resolver()->next($snapshot, ['tierart' => 'katze'], 1));

        // Gar keine Antwort: keine Regel trifft, es geht der Reihe nach weiter.
        $this->assertSame(2, $this->resolver()->next($snapshot, [], 1));

        // An einem Schritt ohne Regeln zaehlt allein die Reihenfolge ...
        $this->assertSame(3, $this->resolver()->next($snapshot, ['tierart' => 'hund'], 2));

        // ... und nach dem letzten Schritt ist die Strecke zu Ende.
        $this->assertNull($this->resolver()->next($snapshot, ['tierart' => 'hund'], 4));
    }

    /**
     * Der reale Fall aus dem Referenzfunnel: Die Tierart steht in Schritt 1,
     * Rasse und Groesse in Schritt 3, dazwischen das Alter. "Anderes Tier"
     * soll Rasse und Groesse ueberspringen -- aber nicht das Alter.
     *
     * @return array<string, array{int, string, int}>
     */
    public static function evaluationStepProvider(): array
    {
        return [
            'greift nicht beim Verlassen von Schritt 1' => [1, 'anderes', 2],
            'greift beim Verlassen von Schritt 2' => [2, 'anderes', 4],
            'trifft dort inhaltlich nicht zu' => [2, 'hund', 3],
            'ohne Bezug zur Regel bleibt die Reihenfolge' => [3, 'anderes', 4],
        ];
    }

    #[DataProvider('evaluationStepProvider')]
    public function test_a_rule_is_evaluated_at_its_own_step_not_at_the_step_of_its_question(
        int $currentStep,
        string $answer,
        int $expectedNext,
    ): void {
        $snapshot = $this->snapshot([[
            // Ausgangsfrage steht in Schritt 1 ...
            'source_field_key' => 'tierart',
            'operator' => 'equals',
            'value' => ['anderes'],
            'target_step_position' => 4,
            // ... ausgewertet wird sie aber erst beim Verlassen von Schritt 2.
            'evaluate_at_step_position' => 2,
            'priority' => 10,
        ]], stepCount: 4);

        $this->assertSame($expectedNext, $this->resolver()->next($snapshot, ['tierart' => $answer], $currentStep));
    }

    public function test_without_the_field_a_rule_keeps_working_at_the_step_of_its_question(): void
    {
        // Snapshot, wie ihn ein vor FB-012a veroeffentlichter Funnel traegt:
        // ohne evaluate_at_step_position.
        $snapshot = $this->snapshot([[
            'source_field_key' => 'tierart',
            'operator' => 'equals',
            'value' => ['anderes'],
            'target_step_position' => 4,
            'priority' => 10,
        ]], stepCount: 4);

        // Alte Semantik: Die Regel greift dort, wo ihre Ausgangsfrage steht.
        $this->assertSame(4, $this->resolver()->next($snapshot, ['tierart' => 'anderes'], 1));
        $this->assertSame(3, $this->resolver()->next($snapshot, ['tierart' => 'anderes'], 2));
    }

    public function test_a_rule_pointing_at_a_missing_step_is_skipped(): void
    {
        $snapshot = $this->snapshot([[
            'source_field_key' => 'tierart',
            'operator' => 'answered',
            'value' => null,
            'target_step_position' => 99,
            'priority' => 10,
        ]]);

        $this->assertSame(2, $this->resolver()->next($snapshot, ['tierart' => 'hund'], 1));
    }

    public function test_rules_running_in_circles_are_stopped(): void
    {
        config()->set('funnel.runtime.max_step_visit_factor', 2);

        $snapshot = FunnelSnapshot::fromArray([
            'funnel' => ['public_token' => '01J8ZTESTTOKEN'],
            'steps' => [
                ['position' => 1, 'title' => 'Eins', 'questions' => [['field_key' => 'tierart', 'position' => 1]]],
                ['position' => 2, 'title' => 'Zwei', 'questions' => [['field_key' => 'weiter', 'position' => 1]]],
            ],
            'conditions' => [
                // Schritt 1 springt auf 2, Schritt 2 wieder zurueck auf 1.
                ['source_field_key' => 'tierart', 'operator' => 'answered', 'value' => null, 'target_step_position' => 2, 'priority' => 10],
                ['source_field_key' => 'weiter', 'operator' => 'answered', 'value' => null, 'target_step_position' => 1, 'priority' => 10],
            ],
        ]);

        $this->expectException(FunnelStepCycleException::class);

        $this->resolver()->path($snapshot, ['tierart' => 'hund', 'weiter' => 'ja']);
    }

    public function test_a_straight_funnel_yields_its_steps_in_order(): void
    {
        $snapshot = $this->snapshot([], stepCount: 3);

        $this->assertSame([1, 2, 3], $this->resolver()->path($snapshot, []));
    }
}
