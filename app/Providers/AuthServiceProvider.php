<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Services\TenantTypeService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerTenantTypeGates();

        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new \App\Mail\User\VerifyEmail($url))
                ->to($notifiable->email);
        });

        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new \App\Mail\User\ResetPassword($url))
                ->to($notifiable->email);
        });
    }

    /**
     * Gates aus dem Tenant-Typ (FB-002).
     *
     * Kaeufer-Tenants sehen keine Funnel-Verwaltung, Betreiber-Tenants keinen
     * Marktplatz. Ohne uebergebenen Tenant gilt der aktive Tenant.
     */
    private function registerTenantTypeGates(): void
    {
        Gate::define('funnels.manage', function (User $user, ?Tenant $tenant = null): bool {
            $service = app(TenantTypeService::class);

            return $service->canManageFunnels($tenant ?? $service->currentTenant());
        });

        Gate::define('marketplace.access', function (User $user, ?Tenant $tenant = null): bool {
            $service = app(TenantTypeService::class);

            return $service->canAccessMarketplace($tenant ?? $service->currentTenant());
        });
    }
}
