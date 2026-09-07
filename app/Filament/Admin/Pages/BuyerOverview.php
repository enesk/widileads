<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Services\BuyerOverviewService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * FB-060: Die Sicht des Operators auf seine Kaeufer.
 *
 * Umsatz, Reklamationsquote und wer damit aus dem Rahmen faellt. Die Seite
 * beurteilt niemanden -- sie sagt, wo hinzusehen sich lohnt.
 */
class BuyerOverview extends Page
{
    protected string $view = 'filament.admin.pages.buyer-overview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 15;

    public function getHeading(): string|Htmlable
    {
        return __('marketplace.overview.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('marketplace.overview.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketplace.overview.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return app(BuyerOverviewService::class)->all();
    }

    public function averageComplaintRate(): float
    {
        return app(BuyerOverviewService::class)->averageComplaintRate();
    }

    public function flagThreshold(): int
    {
        return (int) config('funnel.marketplace.buyer_review.complaint_rate_flag_points');
    }
}
