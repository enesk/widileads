<?php

use App\Constants\OnboardingStep;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\Buyer\LeadCallController;
use App\Http\Controllers\Buyer\PaymentMethodController;
use App\Http\Controllers\Buyer\SettlementInvoiceController;
use App\Http\Controllers\Buyer\WalletTopupController;
use App\Http\Controllers\Funnel\FunnelPreviewController;
use App\Http\Controllers\Funnel\LeadExportDownloadController;
use App\Http\Controllers\Funnel\ThemePreviewController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Onboarding\MailPreviewController as OnboardingMailPreviewController;
use App\Http\Controllers\Onboarding\MailUnsubscribeController;
use App\Http\Controllers\PaymentProviders\PaddleController;
use App\Http\Controllers\Portal\Onboarding\PhoneController as PhoneVerificationScreen;
use App\Http\Controllers\Portal\Onboarding\TourSeenController;
use App\Http\Controllers\ProductCheckoutController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Middleware\AddFunnelSecurityHeaders;
use App\Livewire\Funnel\FunnelRunner;
use App\Livewire\Portal\BuyingCriteria as PortalBuyingCriteria;
use App\Livewire\Portal\CallerIdVerification as PortalCallerIdVerification;
use App\Livewire\Portal\Dashboard as PortalDashboard;
use App\Livewire\Portal\LeadDetail as PortalLeadDetail;
use App\Livewire\Portal\Marketplace as PortalMarketplace;
use App\Livewire\Portal\Onboarding\Done as PortalOnboardingDone;
use App\Livewire\Portal\Onboarding\Wizard as PortalOnboardingWizard;
use App\Livewire\Portal\Orders as PortalOrders;
use App\Livewire\Portal\PaymentMethods as PortalPaymentMethods;
use App\Livewire\Portal\Profile as PortalProfile;
use App\Livewire\Portal\PurchasedLeads as PortalPurchasedLeads;
use App\Livewire\Portal\Settlements as PortalSettlements;
use App\Livewire\Portal\TeamLeads as PortalTeamLeads;
use App\Livewire\Portal\TopUpSuccess as PortalTopUpSuccess;
use App\Livewire\Portal\Transactions as PortalTransactions;
use App\Livewire\Portal\WalletTopUp as PortalWalletTopUp;
use App\Models\Funnel;
use App\Services\CompanyProfile;
use App\Services\PlanService;
use App\Services\SessionService;
use App\Services\TenantCreationService;
use App\Services\UserDashboardService;
use App\Support\PortalTour;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
| If you want the URL to be added to the sitemap, add a "sitemapped" middleware to the route (it has to GET route)
|
*/

// LP-ONBOARD-006: Styleguide des Portals. Arbeitsmittel fuer die Abnahme der
// Bausteine, keine Seite fuer Besucher -- deshalb nur ausserhalb der
// Produktionsumgebung erreichbar.
if (! app()->environment('production')) {
    Route::view('/styleguide', 'styleguide')->name('styleguide');
}

// FB-024: Testseite fuer das Embed-Snippet -- simuliert eine fremde Webseite.
// Nur lokal, sie gehoert nicht in eine oeffentliche Umgebung.
if (app()->environment('local', 'testing')) {
    Route::get('/dev/embed-test', function () {
        $funnel = Funnel::query()
            ->withoutGlobalScopes()
            ->when(request()->filled('token'), fn ($query) => $query->where('public_token', request()->string('token')))
            ->whereNotNull('current_version_id')
            ->latest('id')
            ->first();

        return view('dev.embed-test', [
            'token' => $funnel?->public_token,
            'scriptUrl' => url('/embed/v1.js'),
        ]);
    })->name('dev.embed-test');
}

// FB-020: Oeffentliche Funnel-Strecke. Adressiert wird ausschliesslich ueber den
// nicht erratbaren Token der veroeffentlichten Fassung, nie ueber eine ID.
Route::get('/f/{token}', FunnelRunner::class)
    ->name('funnel.run')
    ->middleware(AddFunnelSecurityHeaders::class)
    ->where('token', '[0-9A-Za-z]{26}');

// FB-018: Vorschau des Entwurfsstands. Der signierte, befristete Link ist die
// Zugangskontrolle - deshalb keine Auth-Middleware.
Route::get('/f/{token}/vorschau', FunnelPreviewController::class)
    ->name('funnel.preview')
    ->middleware(['signed', AddFunnelSecurityHeaders::class])
    ->where('token', '[0-9A-Za-z]{26}');

// FB-073: Herunterladen eines fertigen Lead-Exports. Der Link ist signiert und
// befristet; zusaetzlich muss der Abrufende zum Workspace gehoeren.
//
// LP-ONBOARD-003: Und er braucht eine bestaetigte Rufnummer. Die Datei enthaelt
// Kontaktdaten gekaufter Leads -- sie ist damit dieselbe Offenlegung wie der
// Lead-Screen im Portal und faellt unter dieselbe Sperre. Der signierte Link
// allein genuegt nicht: Er ueberlebt das Zuruecksetzen einer Bestaetigung
// (LP-ONBOARD-017) und liegt beim Nutzer in der Mail, nicht hinter einem Screen.
Route::get('/exporte/{export}', LeadExportDownloadController::class)
    ->name('lead-export.download')
    ->middleware(['signed', 'auth', 'portal.phone-verified']);

// FB-017: Live-Vorschau des Theme-Editors, eingebettet als iFrame. Sie zeigt
// den Stand im Formular, nicht den gespeicherten - die Werte kommen deshalb
// aus der Adresse. Zugriff nur fuer angemeldete Nutzer mit Tenant-Kontext.
Route::get('/dashboard/funnels/{funnel}/theme-vorschau', [ThemePreviewController::class, 'show'])
    ->name('funnel.theme-preview')
    ->middleware('auth');

/*
|--------------------------------------------------------------------------
| Onboarding-Mailstrecke (LP-ONBOARD-014)
|--------------------------------------------------------------------------
|
| Zwei Adressen ausserhalb des Portals, beide bewusst ohne Mandanten im Pfad.
|
| Die Abmeldung ist signiert und braucht keine Anmeldung: Sie steht in einer
| Mail, und wer sich abmelden will, soll sich dafuer nicht einloggen. Sie
| schaltet ausschliesslich die Onboarding-Strecke ab -- Belege bleiben
| unberuehrt.
|
| Die Vorschau rendert dieselbe Mailable, die auch verschickt wird (mit
| `?format=text` die Nur-Text-Fassung, mit `?tenant=<uuid>` ein bestimmter
| Workspace). Sie versendet nichts und schreibt nichts; den Zugang
| beschraenkt der Controller auf Plattform-Admins.
|
*/

Route::get('/onboarding-mails/abmelden/{user}', MailUnsubscribeController::class)
    ->name('onboarding-mails.unsubscribe')
    ->middleware('signed');

Route::get('/mail-preview/onboarding-{key}', OnboardingMailPreviewController::class)
    ->name('onboarding-mails.preview')
    ->where('key', '[a-z-]+');

Route::get('/', function () {
    return view('home');
})->name('home')->middleware('sitemapped');

Route::get('/dashboard', function (UserDashboardService $dashboardService) {
    return redirect($dashboardService->getUserDashboardUrl(Auth::user()));
})->name('dashboard')->middleware('auth');

Auth::routes();

Route::get('/plan/start', function (
    TenantCreationService $tenantCreationService,
    SessionService $sessionService,
    PlanService $planService,
) {
    if ($planService->getDefaultProduct() !== null) {
        if (! auth()->check()) {
            $sessionService->setCreateTenantForFreePlanUser(true);
        } else {
            $tenantCreationService->createTenantForFreePlanUser(auth()->user());
        }
    }

    return redirect()->route('register');
})->name('plan.start');

Route::get('/email/verify', function () {
    return view('auth.verify');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    $user = $request->user();
    if ($user->hasVerifiedEmail()) {
        return redirect()->route('registration.thank-you');
    }

    return redirect('/');
})->middleware(['auth', 'signed'])->name('verification.verify');

/*
|--------------------------------------------------------------------------
| Alte SaaSykit-Rufnummernbestaetigung: nur noch Umleitung (LP-ONBOARD-023)
|--------------------------------------------------------------------------
|
| Hier lag der zweite Weg, der `users.phone_number_verified_at` setzen konnte:
| ein Code aus `mt_rand` in der Session, ohne Rate-Limit, ohne E.164 und ohne
| Pruefung, ob die Nummer schon einem anderen Konto gehoert. Damit war jede
| Zusicherung von App\Services\Phone\PhoneVerificationService umgehbar.
|
| Die beiden Adressen bleiben als Umleitung auf `onboarding.phone` bestehen,
| weil im Bestand noch dorthin verwiesen wird (Checkout-Formular und die
| Abo-Karte im Dashboard). Der Name bleibt deshalb ebenfalls erhalten.
|
*/

Route::redirect('/phone/verify', '/onboarding/telefon')
    ->name('user.phone-verify');

Route::redirect('/phone/verified', '/onboarding/telefon')
    ->name('user.phone-verified');

/*
|--------------------------------------------------------------------------
| Onboarding-Gate: die Rufnummer (LP-ONBOARD-003)
|--------------------------------------------------------------------------
|
| Das Ziel der Sperre EnsurePhoneVerified und damit die einzige Seite, die ein
| angemeldeter Nutzer ohne bestaetigte Rufnummer erreicht. Sie liegt bewusst
| OHNE Workspace im Pfad: Die Sperre greift, bevor ein Workspace gewaehlt ist,
| und sie gilt fuer jeden Workspace des Nutzers -- die Bestaetigung haengt am
| Benutzer (`users.phone_number_verified_at`).
|
| Der Fokus-Rahmen ist Absicht: keine Navigation, kein Workspace-Wechsler,
| keine Guthaben-Anzeige. Es werden auch keine Workspace-Daten geladen -- der
| View-Composer fuer den Onboarding-Stand haengt an
| `components.layouts.portal-app` und laeuft hier deshalb nicht.
|
| Ist die Nummer schon bestaetigt, fuehrt die Seite die gemerkte Adresse aus
| (`redirect()->intended`). Das ist die Rueckkehr nach der Sperre und zugleich
| der Weg, den der Knopf der Erfolgsseite nimmt.
|
| Der Inhalt sind die drei Screens aus LP-ONBOARD-005 -- Nummer, Code, Erfolg
| -- als Zustaende EINER Livewire-Komponente
| (App\Livewire\Portal\Onboarding\PhoneVerification). Deshalb gibt es hier nur
| diese eine Adresse: Der Vorgang darf zwischen den Screens nicht neu laden.
|
*/

Route::get('/onboarding/telefon', PhoneVerificationScreen::class)
    ->name('onboarding.phone')
    ->middleware(['auth', 'verified']);

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::get('/registration/thank-you', function () {
    return view('auth.thank-you');
})->middleware('auth')->name('registration.thank-you');

Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])
    ->where('provider', 'google|github|facebook|twitter-oauth-2|linkedin-openid|bitbucket|gitlab')
    ->name('auth.oauth.redirect');

Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])
    ->where('provider', 'google|github|facebook|twitter-oauth-2|linkedin-openid|bitbucket|gitlab')
    ->name('auth.oauth.callback');

Route::get('/checkout/plan/{planSlug}', [
    SubscriptionCheckoutController::class,
    'subscriptionCheckout',
])->name('checkout.subscription');

Route::get('/checkout/convert-subscription/{subscriptionUuid}', [
    SubscriptionCheckoutController::class,
    'convertLocalSubscriptionCheckout',
])->name('checkout.convert-local-subscription');

Route::get('/already-subscribed', function () {
    return view('checkout.already-subscribed');
})->name('checkout.subscription.already-subscribed');

Route::get('/checkout/subscription/success', [
    SubscriptionCheckoutController::class,
    'subscriptionCheckoutSuccess',
])->name('checkout.subscription.success')->middleware('auth');

Route::get('/checkout/convert-subscription-success', [
    SubscriptionCheckoutController::class,
    'convertLocalSubscriptionCheckoutSuccess',
])->name('checkout.convert-local-subscription.success')->middleware('auth');

Route::get('/payment-provider/paddle/payment-link', [
    PaddleController::class,
    'paymentLink',
])->name('payment-link.paddle');

Route::get('/subscription/{subscriptionUuid}/change-plan/{planSlug}/tenant/{tenantUuid}', [
    SubscriptionController::class,
    'changePlan',
])->name('subscription.change-plan')->middleware('auth');

Route::post('/subscription/{subscriptionUuid}/change-plan/{planSlug}/tenant/{tenantUuid}', [
    SubscriptionController::class,
    'changePlan',
])->name('subscription.change-plan.post')->middleware('auth');

Route::get('/subscription/change-plan-thank-you', [
    SubscriptionController::class,
    'success',
])->name('subscription.change-plan.thank-you')->middleware('auth');

// blog (nur registriert, wenn das Feature-Flag gesetzt ist - siehe config/funnel.php)
if (config('funnel.features.blog')) {
    Route::controller(BlogController::class)
        ->prefix('/blog')
        ->group(function () {
            Route::get('/', 'all')->name('blog')->middleware('sitemapped');
            Route::get('/category/{slug}', 'category')->name('blog.category');
            Route::get('/{slug}', 'view')->name('blog.view');
        });
}

// AGB und Datenschutzerklaerung (Ticket #33). Beide nennen den Anbieter und
// speisen ihn aus denselben Rechnungseinstellungen wie Impressum und Rechnung.
// Anders als das Impressum bleiben die Seiten auch ohne gepflegte Stammdaten
// erreichbar -- der Anbieterblock bleibt dann leer statt erfunden.
Route::get('/terms-of-service', function (CompanyProfile $company) {
    return view('pages.terms-of-service', ['company' => $company]);
})->name('terms-of-service')->middleware('sitemapped');

Route::get('/privacy-policy', function (CompanyProfile $company) {
    return view('pages.privacy-policy', ['company' => $company]);
})->name('privacy-policy')->middleware('sitemapped');

// Impressum (Paragraf 5 DDG). Die Angaben kommen aus den Rechnungseinstellungen
// im Admin; solange sie fehlen, gibt es die Seite nicht -- und der Portal-Fuss
// verweist auch nicht darauf. Lieber kein Impressum als ein erfundenes.
Route::get('/impressum', function (CompanyProfile $company) {
    abort_unless($company->isComplete(), 404);

    return view('pages.imprint', ['company' => $company]);
})->name('imprint')->middleware('sitemapped');

// Product checkout routes

Route::get('/buy/product/{productSlug}/{quantity?}', [
    ProductCheckoutController::class,
    'addToCart',
])->name('buy.product');

Route::get('/checkout/product', [
    ProductCheckoutController::class,
    'productCheckout',
])->name('checkout.product');

Route::get('/checkout/product/success', [
    ProductCheckoutController::class,
    'productCheckoutSuccess',
])->name('checkout.product.success')->middleware('auth');

// roadmap (nur registriert, wenn das Feature-Flag gesetzt ist - siehe config/funnel.php)

if (config('funnel.features.roadmap')) {
    Route::controller(RoadmapController::class)
        ->prefix('/roadmap')
        ->group(function () {
            Route::get('/', 'index')->name('roadmap');
            Route::get('/i/{itemSlug}', 'viewItem')->name('roadmap.viewItem');
            Route::get('/suggest', 'suggest')->name('roadmap.suggest')->middleware('auth');
        });
}

// Invitations

Route::get('/invitations', [
    InvitationController::class,
    'index',
])->name('invitations')->middleware('auth');

// Invoice

Route::controller(InvoiceController::class)
    ->prefix('/invoice')
    ->group(function () {
        Route::get('/generate/{transactionUuid}', 'generate')->name('invoice.generate');
        Route::get('/preview', 'preview')->name('invoice.preview');
    });

/*
|--------------------------------------------------------------------------
| API-Dokumentation (FB-030a)
|--------------------------------------------------------------------------
|
| docs/openapi.yaml und docs/openapi-public.yaml sind die Single Source of Truth
| der beiden APIs. Diese Routen zeigen sie an und liefern sie aus -- erzeugt
| wird hier nichts. Der Contract-Test prueft, dass die Spezifikationen und die
| tatsaechlich registrierten Routen unter /api/v1 und /api/public/v1 nicht
| auseinanderlaufen.
|
| Die oeffentliche Runtime-API kam mit FB-030h dazu. Eine Spezifikation, die
| nur im Repository liegt, ist interne Dokumentation -- FB-026 richtet sich
| aber an fremde Frontends, die sie brauchen.
|
*/

if (config('funnel.api.docs_enabled')) {
    Route::controller(ApiDocsController::class)
        ->prefix('/docs/api')
        ->group(function () {
            Route::get('/', 'index')->name('api-docs');
            Route::get('/openapi.yaml', 'spec')->name('api-docs.spec');

            Route::get('/public', 'publicIndex')->name('api-docs.public');
            Route::get('/openapi-public.yaml', 'publicSpec')->name('api-docs.public.spec');
        });
}

/*
|--------------------------------------------------------------------------
| Kaeufer-Registrierung (FB-050)
|--------------------------------------------------------------------------
|
| Oeffentlich erreichbar, aber angemeldet: das Benutzerkonto entsteht ueber die
| Anmeldung von SaaSykit, dieses Formular ergaenzt nur die kaufmaennischen
| Angaben und legt daraus den Kaeufer-Mandanten an. Der Mandant ist danach
| "pending" -- den Marktplatz erreicht er erst nach der Freischaltung durch den
| Plattform-Admin (Middleware "marketplace.access").
|
*/

Route::get('/kaeufer/registrierung', function () {
    return view('pages.buyer-registration');
})->name('buyer.register')->middleware(['auth', 'portal.phone-verified']);

/*
|--------------------------------------------------------------------------
| Click-to-Call des Kaeufers (FB-081)
|--------------------------------------------------------------------------
|
| Stoesst den Anruf zu einem gekauften Lead an. Angemeldet und je Benutzer
| begrenzt: Jeder Aufruf kostet Geld und laesst beim Lead das Telefon
| klingeln. Der Lead wird ueber seine oeffentliche Kennung angesprochen, der
| passende Kaufbeleg im Controller dazu gesucht.
|
*/

Route::post('/leads/{lead}/call', LeadCallController::class)
    ->middleware(['auth', 'portal.phone-verified', 'throttle:lead-calls'])
    ->name('buyer.leads.call');

/*
|--------------------------------------------------------------------------
| Guthaben aufladen (LP-WALLET-009)
|--------------------------------------------------------------------------
|
| Nimmt den frei gewaehlten Betrag der Aufladeseite an und uebergibt an den
| vorhandenen Einmalkauf-Checkout. Gebucht wird hier nichts -- die Gutschrift
| macht nach bestaetigter Zahlung der Zuhoerer CreditWalletAfterPayment.
|
*/

Route::post('/guthaben/aufladen', [WalletTopupController::class, 'store'])
    ->middleware(['auth', 'portal.phone-verified'])
    ->name('buyer.wallet.topup');

/*
|--------------------------------------------------------------------------
| Zahlungsmittel hinterlegen (LP-POSTPAID-005)
|--------------------------------------------------------------------------
|
| Drei Adressen fuer einen Vorgang, weil Stripe Elements dazwischen im Browser
| laeuft: SetupIntent holen, nach der Eingabe bei Stripe bestaetigen, spaeter
| entfernen. IBAN und Kartennummer gehen dabei direkt an Stripe und beruehren
| diese Anwendung nie.
|
| Der Workspace steht im Rumpf und nicht im Pfad -- wie bei der Aufladung
| daneben, aus demselben Grund: Das Formular gehoert zu einer Portalseite, die
| ihren Mandanten schon hat.
|
*/

/*
 * Die Telefonsperre steht auch vor den Geldwegen des Kaeufers
 * (LP-ONBOARD-003). Es sind die Formularziele der Portalseiten -- ohne sie
 * waere die Sperre eine Sichtsperre: Der gesperrte Nutzer sieht die Seite
 * nicht, koennte aber weiter aufladen, ein Zahlungsmittel hinterlegen oder
 * einen Lead anrufen lassen.
 */
Route::middleware(['auth', 'portal.phone-verified'])->group(function (): void {
    Route::post('/zahlungsmittel/einrichten', [PaymentMethodController::class, 'setupIntent'])
        ->name('buyer.payment-methods.setup-intent');

    Route::post('/zahlungsmittel/bestaetigen', [PaymentMethodController::class, 'confirm'])
        ->name('buyer.payment-methods.confirm');

    Route::delete('/zahlungsmittel/{paymentMethod}', [PaymentMethodController::class, 'destroy'])
        ->name('buyer.payment-methods.destroy');

    /*
     * Der Beleg zu einem eingezogenen Postpaid-Betrag (LP-POSTPAID-015).
     *
     * Ohne Workspace im Pfad, weil der Beleg ueber das Wallet eindeutig einem
     * Kaeufer gehoert -- geprueft wird die Mitgliedschaft in genau diesem
     * Workspace, nicht der Workspace der aufrufenden Seite.
     */
    Route::get('/abrechnungen/{settlement}/beleg', SettlementInvoiceController::class)
        ->name('buyer.settlements.invoice');
});

/*
|--------------------------------------------------------------------------
| Portal fuer Kaeufer und Verkaeufer (Portal Phase 1)
|--------------------------------------------------------------------------
|
| Eigener Bereich ausserhalb der Filament-Panels. Der Pfad /portal beisst sich
| weder mit /admin noch mit /dashboard, die beide unveraendert weiterlaufen,
| bis die jeweilige Portalseite steht.
|
| Mandantenkontext: Die Workspace-UUID steht im Pfad, genau wie im
| Dashboard-Panel ({tenant:uuid}). Damit sind Portaladressen teilbar und
| lesezeichenfaehig, zwei Workspaces koennen in zwei Tabs offen sein, und die
| bestehenden Dashboard-Links lassen sich spaeter eins zu eins uebersetzen.
| Eine Ablage in der Session waere das Gegenteil: ein unsichtbarer Zustand,
| den ein zweiter Tab dem ersten unter den Fuessen wegzieht. Aufgeloest und
| gegen die Mitgliedschaft geprueft wird in ResolvePortalTenant, die den
| Mandanten anschliessend als Filament-Tenant ablegt -- so sehen Global Scope,
| Policies und Services denselben Kontext wie bisher.
|
| Die Platzhalter uebergeben Sprachschluessel statt fertiger Texte, weil
| Route::view-Daten beim Zwischenspeichern der Routen mit eingefroren wuerden.
| Sie tragen ausserdem bewusst einfache Route-Parameter ohne Modellbindung:
| Sie zeigen nur, dass die Adresse steht. Die Bindung entsteht mit der
| jeweiligen Seite.
|
*/

Route::get('/portal', function (UserDashboardService $dashboardService) {
    return redirect($dashboardService->getUserPortalUrl(Auth::user()));
})->name('portal.home')->middleware(['auth', 'verified', 'portal.phone-verified']);

/*
 * Reihenfolge der Middlewares (LP-ONBOARD-003): `auth`, dann `verified`
 * (E-Mail), dann die Telefonsperre `portal.phone-verified` -- und erst danach
 * der Workspace-Kontext `portal.tenant`. Ein gesperrter Nutzer soll den
 * Mandantenkontext gar nicht erst bekommen. Festgeschrieben ist die Reihenfolge
 * nicht hier, sondern in der Prioritaetsliste in bootstrap/app.php; die Liste
 * hier nennt sie in derselben Folge, damit beides zusammen gelesen wird.
 *
 * Die Sperre gilt fuer Kaeufer- UND Verkaeuferseiten dieser Gruppe -- eine
 * Regel fuer alle. Die Wizard-Schritte (LP-ONBOARD-006 ff.) gelten dagegen nur
 * fuer Kaeufer-Workspaces und stehen nicht in der Routen-Middleware.
 *
 * Eine zweite Sperre auf die bestaetigte Rufnummernanzeige gibt es hier nicht
 * mehr (Ticket #49). Sie unterschied sich von der Telefonsperre in genau einem
 * Fall -- einer unbrauchbaren `caller_ids`-Zeile -- und tat dort das Falsche:
 * Sie verlangte einen zweiten Nachweis fuer eine Nummer, die Twilio Verify
 * kurz vorher bestaetigt hatte. Die Rufnummernanzeige ist Voraussetzung fuer
 * den Anruf und nicht fuer das Portal; geprueft wird sie im CallService
 * (`caller_id_missing`), und Dashboard und Lead-Detail weisen den Weg dorthin.
 */
Route::middleware(['auth', 'verified', 'portal.phone-verified', 'portal.tenant', 'portal.wizard'])
    ->prefix('portal/{tenant:uuid}')
    ->name('portal.')
    ->group(function () {
        /*
         | Der Einrichtungs-Wizard (LP-ONBOARD-007)
         |----------------------------------------------------------------
         |
         | Ein Schritt je Adresse -- `industries`, `region`, `payment`,
         | eingeschraenkt auf genau diese drei. Der Schritt steht im Pfad und
         | nicht in der Sitzung: Zurueck und Vorwaerts des Browsers sollen
         | tun, was sie sagen, und ein Neuladen auf demselben Schritt landen.
         |
         | Die Seite laeuft im Fokus-Rahmen, nicht im Arbeitsbereich: Wer hier
         | ist, hat genau eine Aufgabe und soll nicht nebenbei im Marktplatz
         | stoebern, der ihm noch nichts zeigen kann.
         |
         | `portal.wizard` ist der Rest der Gruppe und nicht diese Route:
         | Die Middleware fuehrt JEDE andere Portaladresse hierher, dieser
         | Routenname ist in ihrer Ausnahmeliste.
         */
        Route::get('/onboarding/{step}', PortalOnboardingWizard::class)
            ->name('onboarding')
            ->whereIn('step', OnboardingStep::wizardSlugs());

        /*
         | Der Abschluss-Screen des Wizards (LP-ONBOARD-011)
         |----------------------------------------------------------------
         |
         | Das Ziel von Schritt 3: nach der Zahlung, nach "Spaeter, erst mal
         | umschauen" und nach dem Verweis auf der Erfolgsseite der Aufladung.
         |
         | Links der Beispiel-Lead mit seinen drei Markern, rechts der
         | 3-Schritte-Player "So wird abgerechnet" (LP-ONBOARD-011). Die Seite
         | speichert nichts und hakt nichts ab -- sie erklaert und fuehrt zum
         | Marktplatz.
         |
         | Der Name faellt unter `portal.onboarding.*` und damit in die
         | Ausnahmeliste des Wizard-Gates: Wer ihn ueber die Zahlung erreicht,
         | hat den Wizard abgeschlossen -- wer ihn vorher aufruft, soll nicht
         | in eine Umleitungsschleife laufen.
         */
        Route::get('/onboarding/fertig', PortalOnboardingDone::class)
            ->name('onboarding.done');

        /*
         | "Diese Tour ist durch" (LP-ONBOARD-013)
         |----------------------------------------------------------------
         |
         | Die einzige Adresse der gefuehrten Touren. Sie zeigt nichts an: Das
         | Skript meldet hierher, wenn der Kaeufer die Tour beendet oder
         | uebersprungen hat, und bleibt auf der Seite.
         |
         | Der Tourname ist auf die bekannten Namen eingeschraenkt -- ein
         | Tippfehler endet damit in 404 und nicht in einem Haken, den niemand
         | liest. Der Name faellt unter `portal.onboarding.*` und damit in die
         | Ausnahmeliste des Wizard-Gates; die Meldung soll nicht in einer
         | Umleitung verschwinden.
         */
        Route::post('/onboarding/tour/{name}/seen', TourSeenController::class)
            ->name('onboarding.tour.seen')
            ->whereIn('name', PortalTour::names());

        // Das Dashboard nach dem Entwurf dashboard.html: oben der
        // Handlungsbedarf, unten die Statistik.
        Route::get('/', PortalDashboard::class)
            ->name('overview');

        // Kaeufer
        // Die erste fertig gebaute Portalseite (Portal Phase 1). Der
        // Mandantenkontext haengt bei Livewire-Folgeanfragen nicht mehr an
        // dieser Route -- die Komponente stellt ihn selbst wieder her.
        Route::get('/marktplatz', PortalMarketplace::class)
            ->name('marketplace');
        // Zweite fertige Portalseite: "Meine Leads" nach dem Entwurf
        // meine-leads.html. Wie beim Marktplatz stellt die Komponente den
        // Mandantenkontext bei Folgeanfragen selbst wieder her.
        Route::get('/leads', PortalPurchasedLeads::class)
            ->name('leads');
        // "Team Leads": alle Kaeufe des Mandanten mit dem Kaeufer je Zeile
        // (Ticket #3). Zugang nur mit dem Recht `view team leads`, geprueft in
        // der Komponente -- nicht in der Middleware, weil auch jede
        // Livewire-Folgeanfrage durch dieselbe Pruefung muss.
        Route::get('/team-leads', PortalTeamLeads::class)
            ->name('team-leads');
        // Dritte fertige Portalseite: der gekaufte Lead nach dem Entwurf
        // lead-detail.html. Der Parameter bleibt der Schluessel des
        // Kaufbelegs; geladen und gegen den Kaeufer geprueft wird in der
        // Komponente.
        Route::get('/leads/{purchase}', PortalLeadDetail::class)
            ->name('leads.show');
        // Vierte fertige Portalseite: "Guthaben aufladen" nach dem Entwurf
        // guthaben-aufladen.html. Das Formular der Seite geht weiterhin an
        // WalletTopupController und von dort in den vorhandenen Checkout --
        // die Komponente zeigt nur an.
        Route::get('/guthaben', PortalWalletTopUp::class)
            ->name('wallet');
        // Rueckkehr vom Zahlungsanbieter. Die Seite bucht nichts -- sie sieht
        // nach, ob der Zuhoerer CreditWalletAfterPayment die Gutschrift schon
        // ins Journal geschrieben hat, und fragt so lange nach.
        Route::get('/guthaben/erfolg', PortalTopUpSuccess::class)
            ->name('wallet.success');
        // Das Journal des Kaeufer-Wallets: jede Bewegung mit Vorzeichen und
        // Kontostand. Gebucht wird ausschliesslich im WalletService, diese
        // Seite liest nur.
        Route::get('/transaktionen', PortalTransactions::class)
            ->name('transactions');
        // Das persoenliche Konto. Loest die Breezy-Seite des Dashboard-Panels
        // ab, auf die das Kontomenue bisher zeigte.
        Route::get('/profil', PortalProfile::class)
            ->name('profile');
        Route::view('/kaeufer-profil', 'portal.placeholder', ['title' => 'portal.pages.buyer_profile'])
            ->name('buyer-profile');

        // Drei Adressen ueber die Ticketliste hinaus: Die parallel entstehende
        // Portalnavigation (Ticket #3) verweist bereits auf sie, und ein
        // Navigationseintrag auf einen unbekannten Routennamen wirft beim
        // Rendern. Sie entsprechen den vorhandenen Dashboard-Seiten
        // Kaufkriterien, Eigene Rufnummer und Bestellungen.
        // Kaufkriterien nach dem Entwurf kaufkriterien.html. Ausgewertet
        // werden sie weiterhin ausschliesslich vom LeadMatcher.
        Route::get('/kaufkriterien', PortalBuyingCriteria::class)
            ->name('buying-criteria');
        // Die eigene Rufnummer bestaetigen, nach dem Entwurf
        // rufnummer-bestaetigen.html. Bestaetigt wird weiterhin nur, was Twilio
        // an den Rueckruf meldet.
        Route::get('/rufnummer', PortalCallerIdVerification::class)
            ->name('caller-id');
        // Bestellungen nach dem Entwurf bestellungen.html. Jede Bestellung
        // ist eine Guthaben-Aufladung; was einzelne Leads gekostet haben,
        // steht im Guthabenverlauf.
        Route::get('/bestellungen', PortalOrders::class)
            ->name('orders');

        /*
         * Pay as you go im Portal (LP-POSTPAID-010).
         *
         * Zwei Adressen: die hinterlegten Zahlungsmittel und die Liste der
         * Einzuege. Der Antrag selbst hat keine eigene Seite -- er steht als
         * Abschnitt auf der Guthabenseite, weil dort die Frage aufkommt.
         *
         * Beide sind auch fuer einen Prepaid-Kaeufer erreichbar: Ein
         * Zahlungsmittel muss hinterlegt sein, BEVOR beantragt werden kann,
         * und die Abrechnungen eines zurueckgestuften Kaeufers bleiben
         * einsehbar.
         */
        Route::get('/zahlungsmittel', PortalPaymentMethods::class)
            ->name('payment-methods');
        Route::get('/abrechnungen', PortalSettlements::class)
            ->name('settlements');

        // Verkaeufer
        Route::view('/funnels', 'portal.placeholder', ['title' => 'portal.pages.funnels'])
            ->name('funnels');
        Route::view('/einnahmen', 'portal.placeholder', ['title' => 'portal.pages.earnings'])
            ->name('earnings');
        Route::view('/auszahlung', 'portal.placeholder', ['title' => 'portal.pages.payout'])
            ->name('payout');
        Route::view('/berichte', 'portal.placeholder', ['title' => 'portal.pages.reports'])
            ->name('reports');

        // Gemeinsam
        Route::view('/einstellungen', 'portal.placeholder', ['title' => 'portal.pages.settings'])
            ->name('settings');
    });
