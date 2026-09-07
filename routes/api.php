<?php

use App\Constants\TenantApiAbility;
use App\Http\Controllers\Api\PublicV1\FunnelSessionController;
use App\Http\Controllers\Api\PublicV1\FunnelStructureController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\PaymentProviders\CreemController;
use App\Http\Controllers\PaymentProviders\LemonSqueezyController;
use App\Http\Controllers\PaymentProviders\PaddleController;
use App\Http\Controllers\PaymentProviders\PolarController;
use App\Http\Controllers\PaymentProviders\StripeController;
use App\Http\Middleware\EnsureAllowedFunnelOrigin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/payments-providers/stripe/webhook', [
    StripeController::class,
    'handleWebhook',
])->name('payments-providers.stripe.webhook');

Route::post('/payments-providers/paddle/webhook', [
    PaddleController::class,
    'handleWebhook',
])->name('payments-providers.paddle.webhook');

Route::post('/payments-providers/lemon-squeezy/webhook', [
    LemonSqueezyController::class,
    'handleWebhook',
])->name('payments-providers.lemon-squeezy.webhook');

Route::post('/payments-providers/creem/webhook', [
    CreemController::class,
    'handleWebhook',
])->name('payments-providers.creem.webhook');

Route::post('/payments-providers/polar/webhook', [
    PolarController::class,
    'handleWebhook',
])->name('payments-providers.polar.webhook');

/*
|--------------------------------------------------------------------------
| Funnel Builder API v1 (FB-006)
|--------------------------------------------------------------------------
|
| Jede Route unter /api/v1 authentifiziert sich ueber ein Sanctum-Token, das
| einem Tenant gehoert. "tenant.from-token" setzt daraus den Tenant-Kontext,
| damit eine Anfrage niemals Daten eines anderen Tenants erreichen kann.
| Fachliche Endpunkte kommen ab FB-030 dazu und tragen zusaetzlich die
| Ability-Pruefung, z.B. ->middleware('ability:funnels:read').
|
*/

Route::middleware(['auth:sanctum', 'tenant.from-token'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function () {
        Route::get('/me', [TenantController::class, 'show'])->name('me');

        // Minimale Sonde fuer die Ability-Pruefung. Die fachlichen Endpunkte ab
        // FB-030 tragen dieselbe Middleware mit ihrer jeweiligen Ability.
        Route::get('/ping/leads', [TenantController::class, 'ping'])
            ->middleware('ability:'.TenantApiAbility::LEADS_READ->value)
            ->name('ping.leads');

        // FB-030d: Leads lesen. Die Maskierung entscheidet der Server je Lead
        // ueber Lead::contactFor() -- nicht der Client und nicht ein Parameter.
        Route::middleware('ability:'.TenantApiAbility::LEADS_READ->value)->group(function () {
            Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
            Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        });
    });

/*
|--------------------------------------------------------------------------
| Oeffentliche Runtime-API (FB-026)
|--------------------------------------------------------------------------
|
| Fuer eigene Frontends, die die Strecke selbst rendern. Kein Token, keine
| Anmeldung -- adressiert wird ueber den oeffentlichen Funnel-Token und den
| Sitzungstoken, beide nicht erratbar. Die Herkunftspruefung aus FB-025 gilt
| hier genauso wie fuer die eingebettete Strecke.
|
*/
Route::prefix('public/v1')
    ->name('api.public.v1.')
    ->middleware([EnsureAllowedFunnelOrigin::class, 'throttle:funnel-public'])
    // Ohne dies suchte Laravel die Sitzung ueber eine Beziehung am Funnel. Die
    // gibt es nicht -- Sitzungen haengen an der Funnel-FASSUNG. Die
    // Zugehoerigkeit prueft der Controller deshalb selbst, und zwar gegen die
    // aktuelle Fassung.
    ->withoutScopedBindings()
    ->group(function () {
        Route::get('/funnels/{funnel:public_token}', [FunnelStructureController::class, 'show'])
            ->name('funnels.show');

        Route::post('/funnels/{funnel:public_token}/sessions', [FunnelSessionController::class, 'store'])
            ->name('sessions.store');

        Route::patch('/funnels/{funnel:public_token}/sessions/{session:token}/answers', [FunnelSessionController::class, 'updateAnswers'])
            ->name('sessions.answers');

        Route::post('/funnels/{funnel:public_token}/sessions/{session:token}/submit', [FunnelSessionController::class, 'submit'])
            ->name('sessions.submit');
    });
