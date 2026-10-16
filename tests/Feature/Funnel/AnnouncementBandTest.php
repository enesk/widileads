<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantType;
use App\Models\Announcement;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Tenant;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\FeatureTest;

/**
 * Das Ankuendigungsband an seinen zwei Orten (#95).
 *
 * Geprueft wird die Einbindung, nicht die Logik: Dass eine aktive
 * Ankuendigung im Zeitfenster auf der Startseite und im Portal erscheint, dass
 * der Funktionsschalter sie an beiden Orten verschwinden laesst, dass sie
 * ausserhalb ihres Zeitfensters nicht erscheint -- und dass jeder Ort nur
 * zeigt, was im Admin fuer ihn eingestellt ist.
 *
 * Der Baustein ist lazy (`#[Lazy]`), seine erste Antwort ist sonst nur ein
 * Platzhalter. Deshalb `Livewire::withoutLazyLoading()`: Der Test will den
 * Inhalt im Seitenquelltext sehen, nicht den Nachlader.
 */
class AnnouncementBandTest extends FeatureTest
{
    /**
     * @var list<string>
     */
    protected array $enabledFunnelFeatures = ['announcements'];

    protected function setUp(): void
    {
        parent::setUp();

        Livewire::withoutLazyLoading();

        // Die Testdaten bringen eigene Ankuendigungen mit; dieser Test will
        // nur die seine sehen.
        Announcement::query()->delete();
    }

    public function test_the_home_page_shows_an_active_announcement(): void
    {
        $announcement = $this->announcement(['show_on_frontend' => true]);

        $this->get('/')
            ->assertSuccessful()
            ->assertSee($announcement->content);
    }

    public function test_the_home_page_shows_no_band_when_the_feature_is_off(): void
    {
        $announcement = $this->announcement(['show_on_frontend' => true]);

        config(['funnel.features.announcements' => false]);

        $this->get('/')
            ->assertSuccessful()
            ->assertDontSee($announcement->content);
    }

    public function test_the_home_page_shows_no_band_outside_the_time_window(): void
    {
        $announcement = $this->announcement([
            'show_on_frontend' => true,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $this->get('/')
            ->assertSuccessful()
            ->assertDontSee($announcement->content);
    }

    /**
     * Beide Platzierungen zeigen, was im Admin fuer sie eingestellt ist -- und
     * nur das. Eine Ankuendigung, die nur im Arbeitsbereich stehen soll, darf
     * auf der oeffentlichen Startseite nicht auftauchen.
     */
    public function test_the_home_page_ignores_an_announcement_meant_for_the_workspace(): void
    {
        $announcement = $this->announcement([
            'show_on_frontend' => false,
            'show_on_user_dashboard' => true,
        ]);

        $this->get('/')
            ->assertSuccessful()
            ->assertDontSee($announcement->content);
    }

    public function test_the_workspace_shows_an_active_announcement(): void
    {
        $announcement = $this->announcement(['show_on_user_dashboard' => true]);

        $this->getWorkspace()
            ->assertSuccessful()
            ->assertSee($announcement->content);
    }

    public function test_the_workspace_shows_no_band_when_the_feature_is_off(): void
    {
        $announcement = $this->announcement(['show_on_user_dashboard' => true]);

        config(['funnel.features.announcements' => false]);

        $this->getWorkspace()
            ->assertSuccessful()
            ->assertDontSee($announcement->content);
    }

    public function test_the_workspace_shows_no_band_outside_the_time_window(): void
    {
        $announcement = $this->announcement([
            'show_on_user_dashboard' => true,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $this->getWorkspace()
            ->assertSuccessful()
            ->assertDontSee($announcement->content);
    }

    public function test_the_workspace_ignores_an_announcement_meant_for_the_home_page(): void
    {
        $announcement = $this->announcement([
            'show_on_frontend' => true,
            'show_on_user_dashboard' => false,
        ]);

        $this->getWorkspace()
            ->assertSuccessful()
            ->assertDontSee($announcement->content);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function announcement(array $attributes = []): Announcement
    {
        return Announcement::factory()->create(array_merge([
            'title' => 'Wartung',
            'content' => 'Wartung heute Abend ab 22 Uhr.',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'show_on_frontend' => false,
            'show_on_user_dashboard' => false,
        ], $attributes));
    }

    /**
     * Ein Kaeufer auf seiner Portal-Startseite -- so aufgebaut wie im
     * PanelSmokeTest, damit der Test an derselben Stelle steht wie der Betrieb.
     *
     * @return TestResponse<Response>
     */
    private function getWorkspace(): TestResponse
    {
        $tenant = Tenant::factory()->create(['type' => TenantType::BUYER]);

        BuyerRegistration::factory()->approved()->create(['tenant_id' => $tenant->id]);

        $user = $this->createUser($tenant, [
            TenancyPermissionConstants::PERMISSION_MANAGE_API_TOKENS,
        ]);

        CallerId::factory()->verified()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);

        return $this->actingAs($user)
            ->get(route('portal.overview', ['tenant' => $tenant->refresh()->uuid]));
    }
}
