<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\FunnelStatus;
use App\Constants\QuestionType;
use App\Exceptions\FunnelNotPublishableException;
use App\Exceptions\FunnelVersionIsImmutableException;
use App\Funnel\Conditions\StepResolver;
use App\Funnel\Results\ResultResolver;
use App\Funnel\Scoring\ScoreCalculator;
use App\Models\Funnel;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FeatureTest;

/**
 * FB-014: Veroeffentlichung erzeugt einen unveraenderlichen Snapshot.
 *
 * Kernversprechen: Was der Endkunde sieht, haengt an der veroeffentlichten
 * Fassung -- nicht am Entwurf, an dem weitergebaut wird.
 */
class PublishFunnelTest extends FeatureTest
{
    private function publish(): PublishFunnel
    {
        return app(PublishFunnel::class);
    }

    /**
     * Vollstaendiger Funnel: zwei Schritte, Auswahlfrage mit Punkten,
     * Kontaktfeld und lueckenlose Ergebnisbereiche.
     */
    private function publishableFunnel(): Funnel
    {
        $funnel = Funnel::factory()->create(['name' => 'Pfotencheck']);

        $questionStep = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 1]);
        $question = FunnelQuestion::factory()->create([
            'step_id' => $questionStep->id,
            'field_key' => 'tierart',
            'type' => QuestionType::SINGLE_CHOICE,
            'label' => 'Welches Tier?',
            'position' => 1,
        ]);
        FunnelOption::factory()->create(['question_id' => $question->id, 'value' => 'hund', 'label' => 'Hund', 'score' => 3, 'position' => 1]);
        FunnelOption::factory()->create(['question_id' => $question->id, 'value' => 'katze', 'label' => 'Katze', 'score' => 1, 'position' => 2]);

        $contactStep = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 2]);
        FunnelQuestion::factory()->create([
            'step_id' => $contactStep->id,
            'field_key' => 'email',
            'type' => QuestionType::EMAIL,
            'label' => 'E-Mail-Adresse',
            'position' => 1,
        ]);

        FunnelResult::factory()->forScoreRange(0, 2)->create(['funnel_id' => $funnel->id, 'title' => 'Geringes Risiko']);
        FunnelResult::factory()->forScoreRange(3, 3)->create(['funnel_id' => $funnel->id, 'title' => 'Hohes Risiko']);

        return $funnel->refresh();
    }

    public function test_changes_to_the_draft_do_not_change_the_published_funnel(): void
    {
        $funnel = $this->publishableFunnel();
        $version = $this->publish()->handle($funnel);

        // Entwurf umbauen: Frage umbenennen, Punkte aendern, Schritt ergaenzen.
        $question = $funnel->questions()->where('field_key', 'tierart')->sole();
        $question->update(['label' => 'Ganz andere Frage']);
        $question->options()->where('value', 'hund')->update(['score' => 99]);
        FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 3]);
        FunnelResult::factory()->forScoreRange(4, 99)->create(['funnel_id' => $funnel->id, 'title' => 'Neu erfunden']);

        $published = $version->refresh()->toSnapshot();

        // Die veroeffentlichte Fassung kennt nichts davon.
        $this->assertSame('Welches Tier?', $published->questions()[0]->label);
        $this->assertSame(2, $published->stepCount());
        $this->assertSame(3, app(ScoreCalculator::class)->calculate($published, ['tierart' => 'hund']));
        $this->assertSame('Hohes Risiko', app(ResultResolver::class)->resolveForAnswers($published, ['tierart' => 'hund'])?->title);
        $this->assertSame([1, 2], app(StepResolver::class)->path($published, ['tierart' => 'hund']));

        // Erst eine erneute Veroeffentlichung uebernimmt den neuen Stand.
        $second = $this->publish()->handle($funnel->refresh());

        $this->assertSame('Ganz andere Frage', $second->toSnapshot()->questions()[0]->label);
        $this->assertSame(3, $second->toSnapshot()->stepCount());
    }

    public function test_versions_are_numbered_consecutively_per_funnel(): void
    {
        $first = $this->publishableFunnel();
        $second = $this->publishableFunnel();

        $this->assertSame(1, $this->publish()->handle($first)->version);
        $this->assertSame(2, $this->publish()->handle($first->refresh())->version);
        $this->assertSame(3, $this->publish()->handle($first->refresh())->version);

        // Der zweite Funnel zaehlt eigenstaendig.
        $versionOfSecond = $this->publish()->handle($second);

        $this->assertSame(1, $versionOfSecond->version);

        $first->refresh();

        $this->assertSame(FunnelStatus::PUBLISHED, $first->status);
        $this->assertSame(3, $first->currentVersion?->version);
        $this->assertSame($versionOfSecond->id, $second->refresh()->current_version_id);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function incompleteFunnelProvider(): array
    {
        return [
            'ohne Schritte' => ['no_steps', 'keinen einzigen Schritt'],
            'ohne Kontaktfeld' => ['no_contact', 'fehlt ein Kontaktfeld'],
            'mit Ergebnisluecke' => ['result_gap', 'kein Ergebnis'],
        ];
    }

    #[DataProvider('incompleteFunnelProvider')]
    public function test_an_incomplete_funnel_is_rejected(string $defect, string $expectedMessagePart): void
    {
        $funnel = match ($defect) {
            'no_steps' => Funnel::factory()->create(),
            'no_contact' => tap($this->publishableFunnel(), function (Funnel $funnel): void {
                $funnel->questions()->where('field_key', 'email')->delete();
            }),
            'result_gap' => tap($this->publishableFunnel(), function (Funnel $funnel): void {
                $funnel->results()->where('min_score', 0)->delete();
            }),
        };

        try {
            $this->publish()->handle($funnel->refresh());
            $this->fail('Der unvollstaendige Funnel haette nicht veroeffentlicht werden duerfen.');
        } catch (FunnelNotPublishableException $exception) {
            $this->assertStringContainsString($expectedMessagePart, implode(' ', $exception->reasons));
        }

        $this->assertSame(FunnelStatus::DRAFT, $funnel->refresh()->status);
        $this->assertNull($funnel->current_version_id);
        $this->assertSame(0, $funnel->versions()->count());
    }

    public function test_a_published_version_cannot_be_changed_or_deleted(): void
    {
        $version = $this->publish()->handle($this->publishableFunnel());

        $this->assertThrows(
            fn () => $version->update(['version' => 99]),
            FunnelVersionIsImmutableException::class,
        );

        $this->assertThrows(fn () => $version->delete(), FunnelVersionIsImmutableException::class);

        $this->assertDatabaseHas('funnel_versions', ['id' => $version->id, 'version' => 1]);
    }
}
