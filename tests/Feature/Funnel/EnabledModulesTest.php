<?php

namespace Tests\Feature\Funnel;

use App\Filament\Admin\Pages\ReferralSettings;
use App\Filament\Admin\Resources\Announcements\AnnouncementResource;
use App\Filament\Admin\Resources\BlogPostCategories\BlogPostCategoryResource;
use App\Filament\Admin\Resources\BlogPosts\BlogPostResource;
use App\Filament\Admin\Resources\Referrals\ReferralResource;
use App\Filament\Admin\Resources\RoadmapItems\RoadmapItemResource;
use Tests\Feature\FeatureTest;

/**
 * FB-001: Gegenprobe zu DisabledModulesTest - mit gesetzten Feature-Flags sind
 * Routen und Navigationseintraege wieder vorhanden.
 */
class EnabledModulesTest extends FeatureTest
{
    /**
     * @var list<string>
     */
    protected array $enabledFunnelFeatures = ['blog', 'roadmap', 'announcements', 'referral'];

    public function test_blog_and_roadmap_routes_are_registered(): void
    {
        $this->get('/blog')->assertSuccessful();
        $this->get('/roadmap')->assertSuccessful();
    }

    public function test_frontend_navigation_links_to_blog_and_roadmap(): void
    {
        config(['app.roadmap_enabled' => true]);

        $response = $this->get('/')->assertSuccessful();

        $response->assertSee(route('blog'), false);
        $response->assertSee(route('roadmap'), false);
    }

    public function test_admin_resources_of_enabled_modules_are_accessible(): void
    {
        $this->actingAs($this->createAdminUser());

        $this->assertTrue(BlogPostResource::canAccess());
        $this->assertTrue(BlogPostCategoryResource::canAccess());
        $this->assertTrue(RoadmapItemResource::canAccess());
        $this->assertTrue(AnnouncementResource::canAccess());
        $this->assertTrue(ReferralResource::canAccess());
        $this->assertTrue(ReferralSettings::canAccess());

        $this->get(BlogPostResource::getUrl('index', [], true, 'admin'))->assertSuccessful();
        $this->get(RoadmapItemResource::getUrl('index', [], true, 'admin'))->assertSuccessful();
        $this->get(AnnouncementResource::getUrl('index', [], true, 'admin'))->assertSuccessful();
    }
}
