<?php

namespace Tests\Feature\Http\Controllers;

use App\Livewire\Announcement\View as AnnouncementView;
use App\Models\Announcement;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Das Ankuendigungsband: aktiv wird gezeigt, inaktiv nicht.
 *
 * Geprueft wird der Baustein selbst, nicht die Startseite: Dass das Band dort
 * (und im Arbeitsbereich) tatsaechlich haengt, steht seit #95 in
 * Tests\Feature\Funnel\AnnouncementBandTest.
 */
class HomeControllerTest extends FeatureTest
{
    /**
     * @var list<string>
     */
    protected array $enabledFunnelFeatures = ['announcements'];

    public function test_announcement_is_displayed()
    {
        Announcement::query()->delete();

        $announcement = Announcement::factory()->create([
            'title' => 'Test Announcement',
            'content' => 'Test content 1',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Livewire::withoutLazyLoading();

        Livewire::test(AnnouncementView::class)
            ->assertSee($announcement->content);
    }

    public function test_announcement_is_not_displayed_when_inactive()
    {
        Announcement::query()->delete();

        $announcement = Announcement::factory()->create([
            'title' => 'Test Announcement',
            'content' => 'Test content 1',
            'is_active' => false,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        Livewire::withoutLazyLoading();

        Livewire::test(AnnouncementView::class)
            ->assertDontSee($announcement->content);
    }
}
