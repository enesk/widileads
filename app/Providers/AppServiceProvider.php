<?php

namespace App\Providers;

use App\Actions\CreateLeadFromSession;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Services\LeadPurchaseAction;
use App\Services\LeadPurchaseLookup;
use App\Services\LeadPurchaseUnavailable;
use App\Services\NoLeadPurchases;
use App\Services\PaymentProviders\Creem\CreemProvider;
use App\Services\PaymentProviders\LemonSqueezy\LemonSqueezyProvider;
use App\Services\PaymentProviders\Offline\OfflineProvider;
use App\Services\PaymentProviders\Paddle\PaddleProvider;
use App\Services\PaymentProviders\PaymentService;
use App\Services\PaymentProviders\Polar\PolarProvider;
use App\Services\PaymentProviders\Stripe\StripeProvider;
use App\Services\UserVerificationService;
use App\Services\VerificationProviders\TwilioProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use libphonenumber\PhoneNumberUtil;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        // Jede abgeschlossene Funnel-Einreichung wird zu einem Lead (FB-031).
        // Die Runtime kennt nur das Interface -- ausgetauscht wird hier.
        $this->app->bind(SubmissionReceiver::class, CreateLeadFromSession::class);

        // Bis FB-054 den Kaufvorgang baut, hat niemand einen Lead gekauft --
        // und damit sieht auch niemand Klartext-Kontaktdaten (FB-032). FB-054
        // tauscht nur diese Bindung aus.
        $this->app->bind(LeadPurchaseLookup::class, NoLeadPurchases::class);

        // Der Marktplatz zeigt den Kaufknopf, kauft aber nicht -- bis FB-054
        // den Kaufvorgang baut und diese Bindung austauscht (FB-053).
        $this->app->bind(LeadPurchaseAction::class, LeadPurchaseUnavailable::class);

        // PhoneNumberUtil hat einen privaten Konstruktor und laesst sich deshalb
        // nicht automatisch aufloesen (FB-011, E.164-Normalisierung).
        $this->app->singleton(PhoneNumberUtil::class, static fn (): PhoneNumberUtil => PhoneNumberUtil::getInstance());

        // payment providers
        $this->app->tag([
            StripeProvider::class,
            PaddleProvider::class,
            LemonSqueezyProvider::class,
            CreemProvider::class,
            PolarProvider::class,
            OfflineProvider::class,
        ], 'payment-providers');

        $this->app->bind(PaymentService::class, function () {
            return new PaymentService(...$this->app->tagged('payment-providers'));
        });

        // verification providers
        $this->app->tag([
            TwilioProvider::class,
        ], 'verification-providers');

        $this->app->afterResolving(UserVerificationService::class, function (UserVerificationService $service) {
            $service->setVerificationProviders(...$this->app->tagged('verification-providers'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentAsset::register([
            Js::make('components-script', __DIR__.'/../../resources/js/components.js'),
        ]);
    }
}
