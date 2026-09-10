<?php

namespace App\Providers\Filament;

use App\Constants\AnnouncementPlacement;
use App\Constants\TenancyPermissionConstants;
use App\Filament\Dashboard\Pages\CreateWorkspace;
use App\Filament\Dashboard\Pages\TenantSettings;
use App\Filament\Dashboard\Pages\TwoFactorAuth\TwoFactorAuth;
use App\Http\Middleware\UpdateUserLastSeenAt;
use App\Livewire\AddressForm;
use App\Models\Tenant;
use App\Services\TenantPermissionService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Jeffgreco13\FilamentBreezy\BreezyCore;

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('dashboard')
            ->path('dashboard')
            ->colors([
                // Allianz-Blau, dieselbe Farbe wie in den E-Mails
                // (config('app.email_color_tint')).
                'primary' => [
                    // Markenfarbe der Vorlage. Feste Palette, weil Filament
                    // fuer Knopfflaechen den Ton 600 nimmt: Hier sitzt 600
                    // genau auf der Marke.
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

                // Pastellgruen fuer den Kaufknopf. Eigene Farbe statt der
                // Primaerfarbe, damit der Kauf sich vom Rest abhebt.
                'pastel' => [
                    50 => '#f2faf5',
                    100 => '#e2f4e9',
                    200 => '#c7e9d5',
                    300 => '#a7dabe',
                    400 => '#8ecda9',
                    500 => '#7cc59c',
                    600 => '#6bb98d',
                    700 => '#559a73',
                    800 => '#437a5c',
                    900 => '#345f48',
                    950 => '#1d3a2b',
                ],
            ])
            ->userMenuItems([
                Action::make('admin-panel')
                    ->label(__('Admin Panel'))
                    ->visible(
                        fn () => auth()->user()->isAdmin()
                    )
                    ->url(fn () => route('filament.admin.pages.dashboard'))
                    ->icon('heroicon-s-cog-8-tooth'),
                Action::make('workspace-settings')
                    ->label(__('Workspace Settings'))
                    ->visible(
                        function () {
                            $tenantPermissionService = app(TenantPermissionService::class);

                            return $tenantPermissionService->tenantUserHasPermissionTo(
                                Filament::getTenant(),
                                auth()->user(),
                                TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS
                            );
                        }
                    )
                    ->icon('heroicon-s-cog-8-tooth')
                    ->url(fn () => TenantSettings::getUrl()),
                Action::make('two-factor-auth')
                    ->label(__('2-Factor Authentication'))
                    ->visible(
                        fn () => config('app.two_factor_auth_enabled')
                    )
                    ->url(fn () => TwoFactorAuth::getUrl())
                    ->icon('heroicon-s-lock-closed'),
            ])
            ->discoverResources(in: app_path('Filament/Dashboard/Resources'), for: 'App\\Filament\\Dashboard\\Resources')
            ->discoverPages(in: app_path('Filament/Dashboard/Pages'), for: 'App\\Filament\\Dashboard\\Pages')
            ->pages([
                Dashboard::class,
                CreateWorkspace::class,
            ])
            ->favicon(asset('images/favicon.ico'))
            ->viteTheme('resources/css/filament/dashboard/theme.css')
            ->discoverWidgets(in: app_path('Filament/Dashboard/Widgets'), for: 'App\\Filament\\Dashboard\\Widgets')
            ->widgets([
                AccountWidget::class,
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
            ->renderHook('panels::head.start', function () {
                return view('components.layouts.partials.analytics');
            })
            ->navigationGroups($this->navigationGroups())
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => config('funnel.features.announcements')
                    ? Blade::render("@livewire('announcement.view', ['placement' => '".AnnouncementPlacement::USER_DASHBOARD->value."'])")
                    : ''
            )
            ->authMiddleware([
                Authenticate::class,
            ])->plugins([
                BreezyCore::make()
                    ->myProfile(
                        shouldRegisterUserMenu: true, // Sets the 'account' link in the panel User Menu (default = true)
                        shouldRegisterNavigation: false, // Adds a main navigation item for the My Profile page (default = false)
                        hasAvatars: false, // Enables the avatar upload form component (default = false)
                        slug: 'my-profile' // Sets the slug for the profile page (default = 'my-profile')
                    )
                    ->myProfileComponents([
                        AddressForm::class,
                    ]),
            ])
            ->tenantMenuItems([
                Action::make('create')
                    ->label(__('New Workspace'))
                    ->url(fn () => CreateWorkspace::getUrl())
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn () => config('app.allow_user_to_create_tenants_from_dashboard', false)),
            ])
            ->tenantMenu()
            ->tenant(Tenant::class, 'uuid');
    }

    /**
     * Navigationsgruppen des Tenant-Dashboards.
     *
     * Reihenfolge nach Tenant-Typ (FB-028): oben, was ein Betreiber taeglich
     * braucht, darunter der Kaeufer-Bereich, dann das, was seltener gebraucht
     * wird. Bewusst ohne Icons an den Gruppen: Filament laesst sie entweder
     * dort oder an den Eintraegen zu, und die Eintraege sind die
     * aussagekraeftigere Stelle.
     *
     * Jede Gruppe, die eine Seite verwendet, muss hier stehen. Eine nicht
     * registrierte Gruppe haengt Filament unsortiert hinten an - der Fehler
     * faellt nur auf, wenn man genau hinsieht (FB-028a: die Gruppe hiess
     * "Team", die Seite "Team Management", und die Sortierung griff nie).
     *
     * @return list<NavigationGroup>
     */
    private function navigationGroups(): array
    {
        return [
            NavigationGroup::make()->label(__('builder.groups.funnels')),
            NavigationGroup::make()->label(__('builder.groups.leads')),
            NavigationGroup::make()->label(__('builder.groups.marketplace')),
            NavigationGroup::make()->label(__('builder.groups.billing')),
            NavigationGroup::make()->label(__('builder.groups.settings')),
            NavigationGroup::make()->label(__('Team Management')),

            // Das Empfehlungsprogramm ist abschaltbar. Die Gruppe wird
            // trotzdem bedingungslos registriert: Filament zeigt eine Gruppe
            // ohne sichtbare Eintraege nicht an, und eine Registrierung, die
            // von einem Konfigurationswert abhaengt, waere zum Bootzeitpunkt
            // ausgewertet - eine spaetere Aenderung des Werts wuerde die
            // Gruppe dann still wieder ans Ende ruecken.
            NavigationGroup::make()->label(__('Referrals')),
        ];
    }
}
