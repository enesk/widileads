<?php

namespace Tests\Feature\Funnel;

use App\Constants\AnnouncementPlacement;
use App\Filament\Admin\Pages\ReferralSettings;
use App\Filament\Admin\Resources\Announcements\AnnouncementResource;
use App\Filament\Admin\Resources\BlogPostCategories\BlogPostCategoryResource;
use App\Filament\Admin\Resources\BlogPosts\BlogPostResource;
use App\Filament\Admin\Resources\Referrals\ReferralResource;
use App\Filament\Admin\Resources\RoadmapItems\RoadmapItemResource;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use App\Services\ReferralService;
use Tests\Feature\FeatureTest;

/**
 * FB-001: Blog, Roadmap, Announcements und Referral sind per Feature-Flag
 * abgeschaltet (Default). Weder Routen noch Navigationseintraege duerfen
 * erscheinen.
 */
class DisabledModulesTest extends FeatureTest
{
    public function test_feature_flags_are_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('funnel.features.blog'));
        $this->assertFalse((bool) config('funnel.features.roadmap'));
        $this->assertFalse((bool) config('funnel.features.announcements'));
        $this->assertFalse((bool) config('funnel.features.referral'));
    }

    public function test_blog_routes_are_not_registered(): void
    {
        $this->withExceptionHandling();

        $this->get('/blog')->assertNotFound();
        $this->get('/blog/category/some-category')->assertNotFound();
        $this->get('/blog/some-post')->assertNotFound();

        $this->assertFalse(app('router')->has('blog'));
    }

    public function test_roadmap_routes_are_not_registered(): void
    {
        $this->withExceptionHandling();

        $this->get('/roadmap')->assertNotFound();
        $this->get('/roadmap/i/some-item')->assertNotFound();
        $this->get('/roadmap/suggest')->assertNotFound();

        $this->assertFalse(app('router')->has('roadmap'));
    }

    public function test_frontend_navigation_has_no_blog_or_roadmap_links(): void
    {
        $response = $this->get('/')->assertSuccessful();

        $response->assertDontSee(url('/blog'));
        $response->assertDontSee(url('/roadmap'));
    }

    public function test_admin_navigation_does_not_expose_disabled_modules(): void
    {
        $this->actingAs($this->createAdminUser());

        $response = $this->get('/admin')->assertSuccessful();

        $response->assertDontSee('admin/blog-posts');
        $response->assertDontSee('admin/blog-post-categories');
        $response->assertDontSee('admin/roadmap-items');
        $response->assertDontSee('admin/announcements');
        $response->assertDontSee('admin/referrals');
        $response->assertDontSee('admin/referral-settings');
    }

    public function test_admin_resources_of_disabled_modules_are_not_accessible(): void
    {
        $this->actingAs($this->createAdminUser());

        $this->assertFalse(BlogPostResource::canAccess());
        $this->assertFalse(BlogPostCategoryResource::canAccess());
        $this->assertFalse(RoadmapItemResource::canAccess());
        $this->assertFalse(AnnouncementResource::canAccess());
        $this->assertFalse(ReferralResource::canAccess());
        $this->assertFalse(ReferralSettings::canAccess());

        $this->withExceptionHandling();

        $this->get(BlogPostResource::getUrl('index', [], true, 'admin'))->assertForbidden();
        $this->get(RoadmapItemResource::getUrl('index', [], true, 'admin'))->assertForbidden();
        $this->get(AnnouncementResource::getUrl('index', [], true, 'admin'))->assertForbidden();
    }

    public function test_announcements_are_not_served(): void
    {
        Announcement::factory()->create([
            'is_active' => true,
            'show_on_frontend' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $announcement = app(AnnouncementService::class)
            ->getAnnouncement(AnnouncementPlacement::FRONTEND);

        $this->assertNull($announcement);
    }

    public function test_referral_program_is_disabled(): void
    {
        config(['app.referral.enabled' => true]);

        $this->assertFalse(app(ReferralService::class)->isEnabled());
    }
}
