<?php

namespace App\Providers;

use App\Services\IpHasher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // FB-030f: Die Management-API wird je TOKEN begrenzt, nicht je IP --
        // hinter einer IP koennen viele Kunden sitzen, und ein Token gehoert
        // genau einem Workspace. Wer kein Token hat, faellt auf die IP zurueck.
        RateLimiter::for('funnel-management', function (Request $request) {
            $token = $request->user()?->currentAccessToken();

            return Limit::perMinute((int) config('funnel.api.rate_limit_per_minute'))
                ->by($token?->getKey() ? 'token:'.$token->getKey() : 'ip:'.$request->ip());
        });

        // FB-026: Die oeffentliche Runtime-API kennt keine Anmeldung. Begrenzt
        // wird deshalb je Herkunft und Stunde ueber denselben Schwellwert wie
        // die eingebettete Strecke (config/funnel.php). Gezaehlt wird ueber den
        // IP-Hash aus FB-022 -- die Adresse selbst liegt nirgends vor.
        RateLimiter::for('funnel-public', function (Request $request) {
            return Limit::perHour((int) config('funnel.public.rate_limit_per_hour'))
                ->by(app(IpHasher::class)->hash($request->ip()) ?? 'unknown');
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
