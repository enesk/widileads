<?php

namespace App\Providers\Filament;

use App\Http\Middleware\UpdateUserLastSeenAt;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->colors([
                // Allianz-Blau, dieselbe Farbe wie in den E-Mails
                // (config('app.email_color_tint')).
                'primary' => [
                    // Feste Palette statt einer aus einem einzigen Wert
                    // errechneten: Filament nimmt fuer Knopfflaechen den Ton
                    // 600 und faerbt sie damit dunkler ein, als die Marke ist.
                    // Hier sitzt 600 genau auf Allianz-Blau.
                    50 => '#eef4fb',
                    100 => '#d5e3f4',
                    200 => '#a9c5e7',
                    300 => '#76a2d6',
                    400 => '#3f7ec0',
                    500 => '#1259a4',
                    600 => '#003781',
                    700 => '#002f6d',
                    800 => '#00265a',
                    900 => '#001d45',
                    950 => '#00122c',
                ],
            ])
            ->navigation()
            ->userMenuItems([
                Action::make('user-dashboard')
                    ->label(__('User Dashboard'))
                    ->visible(
                        fn () => true
                    )
                    ->url(fn () => route('dashboard'))
                    ->icon('heroicon-s-face-smile'),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->favicon(asset('images/favicon.ico'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->pages([

            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                UpdateUserLastSeenAt::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups($this->navigationGroups())
            ->plugins([
                BreezyCore::make()
                    ->myProfile(
                        shouldRegisterUserMenu: true, // Sets the 'account' link in the panel User Menu (default = true)
                        shouldRegisterNavigation: false, // Adds a main navigation item for the My Profile page (default = false)
                        hasAvatars: false, // Enables the avatar upload form component (default = false)
                        slug: 'my-profile' // Sets the slug for the profile page (default = 'my-profile')
                    ),
            ])
            ->sidebarCollapsibleOnDesktop();
    }

    /**
     * Navigationsgruppen des Admin-Panels. Die Gruppen der optionalen
     * SaaSykit-Module werden nur registriert, wenn das jeweilige Feature-Flag
     * gesetzt ist (siehe config/funnel.php).
     *
     * @return list<NavigationGroup>
     */
    private function navigationGroups(): array
    {
        $groups = [
            NavigationGroup::make()
                ->label(fn () => (__('Revenue')))
                ->icon('heroicon-s-rocket-launch')
                ->collapsed(),
            NavigationGroup::make()
                ->label(fn () => __('Tenancy'))
                ->icon('heroicon-s-home')
                ->collapsed(),
            NavigationGroup::make()
                ->label(fn () => (__('Product Management')))
                ->icon('heroicon-s-shopping-cart')
                ->collapsed(),
            NavigationGroup::make()
                ->label(fn () => (__('User Management')))
                ->icon('heroicon-s-users')
                ->collapsed(),
            NavigationGroup::make()
                ->label(fn () => (__('Settings')))
                ->icon('heroicon-s-cog')
                ->collapsed(),
        ];

        // FB-028: Die Gruppen des Funnel-Builders stehen vor den mitgelieferten
        // SaaSykit-Gruppen. Vorher lagen Leads, Funnel-Vorlagen, Kaeufer und
        // Guthabenkonto allesamt unter "Settings" - dort sucht sie niemand.
        array_unshift(
            $groups,
            // Bewusst ohne Icon an der Gruppe: Filament laesst Icons entweder
            // an der Gruppe oder an ihren Eintraegen zu, nicht an beidem. Die
            // Eintraege sind die aussagekraeftigere Stelle - und so bricht die
            // Navigation nicht, sobald eine kuenftige Resource ein Icon bekommt.
            NavigationGroup::make()->label(fn () => (__('builder.groups.funnels'))),
            NavigationGroup::make()->label(fn () => (__('builder.groups.leads'))),
            NavigationGroup::make()->label(fn () => (__('builder.groups.marketplace'))),
            NavigationGroup::make()->label(fn () => (__('builder.groups.security'))),
        );

        if (config('funnel.features.announcements')) {
            $groups[] = NavigationGroup::make()
                ->label(fn () => (__('Announcements')))
                ->icon('heroicon-s-megaphone')
                ->collapsed();
        }

        if (config('funnel.features.blog')) {
            $groups[] = NavigationGroup::make()
                ->label(fn () => (__('Blog')))
                ->icon('heroicon-s-newspaper')
                ->collapsed();
        }

        if (config('funnel.features.roadmap')) {
            $groups[] = NavigationGroup::make()
                ->label(fn () => (__('Roadmap')))
                ->icon('heroicon-s-bug-ant')
                ->collapsed();
        }

        return $groups;
    }
}
