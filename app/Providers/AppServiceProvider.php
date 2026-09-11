<?php

namespace App\Providers;

use App\Actions\CreateLeadFromSession;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Http\Middleware\ResolvePortalTenant;
use App\Services\LeadPurchaseAction;
use App\Services\LeadPurchaseLookup;
use App\Services\LeadPurchaseThroughAction;
use App\Services\PaymentProviders\Creem\CreemProvider;
use App\Services\PaymentProviders\LemonSqueezy\LemonSqueezyProvider;
use App\Services\PaymentProviders\Offline\OfflineProvider;
use App\Services\PaymentProviders\Paddle\PaddleProvider;
use App\Services\PaymentProviders\PaymentService;
use App\Services\PaymentProviders\Polar\PolarProvider;
use App\Services\PaymentProviders\Stripe\StripeProvider;
use App\Services\RecordedLeadPurchases;
use App\Services\Twilio\CallerIdValidationClient;
use App\Services\Twilio\OutboundCallClient;
use App\Services\Twilio\TwilioCallerIdValidationClient;
use App\Services\Twilio\TwilioOutboundCallClient;
use App\Services\UserVerificationService;
use App\Services\VerificationProviders\TwilioProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;
use libphonenumber\PhoneNumberUtil;
use Livewire\Livewire;
use Twilio\Exceptions\ConfigurationException as TwilioConfigurationException;
use Twilio\Rest\Client as TwilioClient;

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
        // Seit FB-054 gibt es Kaeufe: Die Auskunft kommt aus `lead_purchases`,
        // damit contactFor() einem Kaeufer nach dem Kauf Klartext liefert.
        $this->app->bind(LeadPurchaseLookup::class, RecordedLeadPurchases::class);

        // Und der Kaufknopf im Marktplatz wirkt (FB-054).
        $this->app->bind(LeadPurchaseAction::class, LeadPurchaseThroughAction::class);

        // Die Twilio-Anbindung fuer Rufnummern-Bestaetigungen (FB-080). Als
        // Bindung, damit Tests den Anruf ersetzen koennen.
        $this->app->bind(CallerIdValidationClient::class, TwilioCallerIdValidationClient::class);

        // Und die Anbindung fuer den Anruf selbst (FB-081).
        $this->app->bind(OutboundCallClient::class, TwilioOutboundCallClient::class);

        // Der Twilio-REST-Client (FB-082). Als Singleton, damit alle Anrufe
        // eines Requests denselben HTTP-Client teilen. Die Aufloesung ist lazy:
        // Wer Twilio nicht anfasst, braucht auch keine Zugangsdaten.
        $this->app->singleton(TwilioClient::class, static function (): TwilioClient {
            $sid = (string) config('twilio.account_sid');
            $token = (string) config('twilio.auth_token');

            if ($sid === '' || $token === '') {
                throw new TwilioConfigurationException('Twilio ist nicht eingerichtet: twilio.account_sid oder twilio.auth_token fehlt.');
            }

            return new TwilioClient($sid, $token);
        });

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
        $this->trustConfiguredProxies();

        $this->keepPortalTenantOnLivewireUpdates();

        FilamentAsset::register([
            Js::make('components-script', __DIR__.'/../../resources/js/components.js'),
        ]);
    }

    /**
     * ResolvePortalTenant auch bei /livewire/update laufen lassen (Portal Phase 1).
     *
     * Folgeanfragen von Livewire gehen an die globale Route /livewire/update und
     * laufen deshalb nicht durch die Middleware der Portalroute. Ohne Mandant
     * steigt der Global Scope aus BelongsToTenant still aus -- eine Komponente
     * wuerde dann die Daten *aller* Mandanten zeigen statt keiner.
     *
     * Livewire baut fuer persistente Middlewares aus dem gemerkten Pfad der
     * Ursprungsseite eine Ersatzanfrage, matcht die Route neu und schickt sie
     * durch die dort freigeschalteten Middlewares. Die Workspace-UUID aus dem
     * Pfad steht dort also zur Verfuegung, und die Reihenfolge aus der
     * Prioritaetsliste (vor SubstituteBindings) bleibt erhalten, weil Laravel
     * die eingesammelten Middlewares nach Prioritaet sortiert.
     *
     * Komponenten ausserhalb des Portals bleiben unberuehrt: Gefiltert wird
     * gegen die Middlewares der Ursprungsroute, und nur die Portalrouten fuehren
     * ResolvePortalTenant.
     *
     * Das ersetzt InteractsWithPortalTenant nicht, sondern sichert es ab: Der
     * Trait traegt nur, solange ihn wirklich jede Portal-Komponente einbindet --
     * eine vergessene Einbindung faellt nicht auf, weil die Seite weiter Daten
     * zeigt. Doppelte Arbeit kostet das nicht: Der Trait uebernimmt einen
     * bereits gesetzten Mandanten mit passender UUID, ohne ihn erneut zu laden.
     */
    private function keepPortalTenantOnLivewireUpdates(): void
    {
        Livewire::addPersistentMiddleware(ResolvePortalTenant::class);
    }

    /**
     * Vertraute Proxys aus config('funnel.trusted_proxies') setzen.
     *
     * Gehoert bewusst hierher und nicht in bootstrap/app.php: Die dortige
     * Middleware-Closure laeuft, waehrend der HTTP-Kernel aufgeloest wird --
     * vor dem Laden der .env. env() liefert dort im Web null, in der Konsole
     * dagegen den richtigen Wert. Die Einstellung sah deshalb gesetzt aus und
     * wirkte im Web nie: Laravel baute jede Adresse mit http, der Browser
     * blockte die Stylesheets, und die ueber die volle URL gerechnete
     * Twilio-Signatur konnte nicht passen.
     *
     * Ueber die Konfiguration statt ueber env(), damit der Wert einen
     * config:cache ueberlebt -- mit gecachter Konfiguration wird die .env gar
     * nicht mehr gelesen.
     */
    private function trustConfiguredProxies(): void
    {
        $proxies = trim((string) config('funnel.trusted_proxies'));

        if ($proxies === '') {
            return;
        }

        TrustProxies::at($proxies === '*' ? '*' : array_map(trim(...), explode(',', $proxies)));
    }
}
