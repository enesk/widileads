<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\PublishFunnel;
use App\Constants\FunnelProgressStyle;
use App\Constants\FunnelThemeFont;
use App\Constants\QuestionType;
use App\Models\Funnel;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use App\Models\FunnelTheme;
use Tests\Feature\FeatureTest;

/**
 * FB-017: Das Theme muss im veroeffentlichten Snapshot landen.
 *
 * Die oeffentliche Strecke liest ausschliesslich Snapshots. Ein Theme, das nur
 * in der Live-Tabelle steht, waere dort unsichtbar - und zwar lautlos: der
 * Funnel liefe weiter, nur eben ungestylt.
 */
class FunnelThemeSnapshotTest extends FeatureTest
{
    public function test_the_theme_is_part_of_the_published_snapshot(): void
    {
        $funnel = $this->publishableFunnel();

        FunnelTheme::factory()->create([
            'funnel_id' => $funnel->id,
            'primary_color' => '#ff6600',
            'secondary_color' => '#334155',
            'background_color' => '#fffaf5',
            'text_color' => '#111827',
            'font' => FunnelThemeFont::INTER,
            'progress_style' => FunnelProgressStyle::STEPS,
            'border_radius' => 16,
            'button_next_label' => 'Weiter zum naechsten Schritt',
        ]);

        $version = app(PublishFunnel::class)->handle($funnel->refresh());

        $theme = $version->snapshot['theme'];

        $this->assertSame('#ff6600', $theme['primary_color']);
        $this->assertSame('#334155', $theme['secondary_color']);
        $this->assertSame('#fffaf5', $theme['background_color']);
        $this->assertSame('#111827', $theme['text_color']);
        $this->assertSame('inter', $theme['font']);
        $this->assertSame('steps', $theme['progress_style']);
        $this->assertSame(16, $theme['border_radius']);
        $this->assertSame('Weiter zum naechsten Schritt', $theme['button_next_label']);

        // Nicht gepflegte Button-Texte stehen aufgeloest im Snapshot, damit die
        // Runtime ihn ohne die Sprachdateien des Betreibers lesen kann.
        $this->assertNotSame('', $theme['button_back_label']);
        $this->assertNotSame('', $theme['button_submit_label']);

        // Und die Leseseite findet es typisiert wieder.
        $read = $version->toSnapshot()->theme;

        $this->assertNotNull($read);
        $this->assertSame('#ff6600', $read->primaryColor);
        $this->assertSame(FunnelThemeFont::INTER, $read->font);
        $this->assertSame(FunnelProgressStyle::STEPS, $read->progressStyle);
        $this->assertSame(16, $read->borderRadius);
    }

    public function test_a_later_theme_change_does_not_touch_the_published_version(): void
    {
        $funnel = $this->publishableFunnel();
        $theme = FunnelTheme::factory()->create(['funnel_id' => $funnel->id, 'primary_color' => '#ff6600']);

        $version = app(PublishFunnel::class)->handle($funnel->refresh());

        $theme->update(['primary_color' => '#00ff00']);

        $this->assertSame('#ff6600', $version->fresh()->snapshot['theme']['primary_color']);
    }

    public function test_a_funnel_without_a_theme_publishes_with_theme_null(): void
    {
        $version = app(PublishFunnel::class)->handle($this->publishableFunnel());

        $this->assertNull($version->snapshot['theme']);
        $this->assertNull($version->toSnapshot()->theme);
    }

    /**
     * Vollstaendiger Funnel wie in PublishFunnelTest: zwei Schritte,
     * Auswahlfrage mit Punkten, Kontaktfeld, lueckenlose Ergebnisbereiche.
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
}
