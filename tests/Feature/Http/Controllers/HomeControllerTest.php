<?php

namespace Tests\Feature\Http\Controllers;

use App\Livewire\Announcement\View as AnnouncementView;
use App\Models\Announcement;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Das Ankuendigungsband: aktiv wird gezeigt, inaktiv nicht.
 *
 * Geprueft wird der Baustein selbst und nicht mehr die Startseite. Die neue
 * Startseite laeuft auf `x-layouts.portal` und traegt den SaaSykit-Kopf nicht
 * mehr, in dem das Band sitzt (`components/layouts/app/header.blade.php`) --
 * damit stand hier ein Test, der das Band auf einer Seite suchte, die es nicht
 * einbindet. Dass das Band auf `/` fehlt, ist eine Produktfrage und liegt als
 * eigenes Ticket bei Uwe; was dieser Test meint, ist der Baustein.
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
