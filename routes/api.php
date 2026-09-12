<?php

use App\Constants\TenantApiAbility;
use App\Http\Controllers\Api\PublicV1\FunnelSessionController;
use App\Http\Controllers\Api\PublicV1\FunnelStructureController;
use App\Http\Controllers\Api\V1\FunnelConditionController;
use App\Http\Controllers\Api\V1\FunnelController;
use App\Http\Controllers\Api\V1\FunnelLifecycleController;
use App\Http\Controllers\Api\V1\FunnelOptionController;
use App\Http\Controllers\Api\V1\FunnelQuestionController;
use App\Http\Controllers\Api\V1\FunnelResultController;
use App\Http\Controllers\Api\V1\FunnelStepController;
use App\Http\Controllers\Api\V1\FunnelStructureController as ManagementFunnelStructureController;
use App\Http\Controllers\Api\V1\FunnelVersionController;
use App\Http\Controllers\Api\V1\FunnelWebhookController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\WebhookDeliveryController;
use App\Http\Controllers\PaymentProviders\CreemController;
use App\Http\Controllers\PaymentProviders\LemonSqueezyController;
use App\Http\Controllers\PaymentProviders\PaddleController;
use App\Http\Controllers\PaymentProviders\PolarController;
use App\Http\Controllers\PaymentProviders\StripeController;
use App\Http\Controllers\Twilio\CallBridgeController;
use App\Http\Controllers\Twilio\CallDialDoneController;
use App\Http\Controllers\Twilio\CallerIdValidationController;
use App\Http\Controllers\Twilio\CallLegStatusController;
use App\Http\Controllers\Twilio\CallMachineDetectionController;
use App\Http\Controllers\Twilio\CallStatusController;
use App\Http\Controllers\Webhooks\StripePostpaidWebhookController;
use App\Http\Middleware\EnsureAllowedFunnelOrigin;
use App\Http\Middleware\EnsureIdempotentRequest;
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

/*
|--------------------------------------------------------------------------
| Postpaid: Zahlungsmittel und Mandate (LP-POSTPAID-005)
|--------------------------------------------------------------------------
|
| Eigener Stripe-Endpunkt neben dem Webhook oben. Jener entscheidet ueber
| Abonnements und Bestellungen, dieser ueber die Zahlungsfaehigkeit eines
| Postpaid-Kaeufers: abgehaengtes Zahlungsmittel, widerrufenes Mandat. Zwei
| Zustaendigkeiten, zwei Endpunkte -- im Stripe-Dashboard getrennt
| abschaltbar, ohne die Aufladungen mitzunehmen.
|
| Die Adresse ist oeffentlich erreichbar und traegt kein Geheimnis. Sie ist
| ausschliesslich durch die Signaturpruefung geschuetzt: Ohne sie koennte
| jeder ein Zahlungsmittel stilllegen und damit eine Rueckstufung ausloesen.
|
*/

Route::post('/payments-providers/stripe/postpaid-webhook', StripePostpaidWebhookController::class)
    ->name('payments-providers.stripe.postpaid-webhook');

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

/*
|--------------------------------------------------------------------------
| Twilio-Rueckrufe (FB-080)
|--------------------------------------------------------------------------
|
| Die Adresse ist oeffentlich erreichbar und traegt kein Geheimnis. Sie ist
| ausschliesslich durch die Signaturpruefung geschuetzt -- ohne sie koennte
| jeder eine fremde Rufnummer als Rufnummernanzeige freischalten.
|
*/

Route::middleware('twilio.signature')->group(function (): void {
    Route::post('/twilio/caller-id/validation-status', CallerIdValidationController::class)
        ->name('twilio.caller-id.validation-status');

    // FB-081/FB-082: Anweisung zum Durchstellen und die Meldungen zum Verlauf.
    // Die Bridge nimmt GET und POST an -- Twilio ruft die Anweisungsadresse je
    // nach Konfiguration mit beiden Verfahren ab.
    Route::match(['get', 'post'], '/twilio/bridge/{attempt}', CallBridgeController::class)
        ->name('twilio.calls.bridge');

    // Stand des Kaeufer-Beins: nimmt der Mitarbeiter ab, klingelt es, legt er
    // auf?
    Route::post('/twilio/calls/{attempt}/status', CallStatusController::class)
        ->name('twilio.calls.status');

    // Ergebnis des Dial-Verbs mit DialCallStatus und DialCallDuration. Hier --
    // und nur hier -- wird bewertet und ueber die Erreichbarkeit entschieden.
    Route::post('/twilio/calls/{attempt}/dial-done', CallDialDoneController::class)
        ->name('twilio.calls.dial-done');

    Route::post('/twilio/calls/{attempt}/leg-status', CallLegStatusController::class)
        ->name('twilio.calls.leg-status');

    Route::post('/twilio/calls/{attempt}/machine-detection', CallMachineDetectionController::class)
        ->name('twilio.calls.machine-detection');
});

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

Route::middleware([
    'auth:sanctum',
    'tenant.from-token',
    // FB-030f: Ratenbegrenzung je Token und Wiederholschutz fuer POST.
    'throttle:funnel-management',
    EnsureIdempotentRequest::class,
])
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

        /*
        | FB-030b: Funnels und ihre Bausteine.
        |
        | Der Funnel wird ueber seinen public_token gebunden, die Bausteine
        | ueber ihre ID -- und zwar mit scopeBindings(): Ein Schritt einer
        | fremden Strecke fuehrt damit zu 404, statt still bearbeitet zu werden.
        | Den Mandantenfilter setzt der Global Scope aus BelongsToTenant, den
        | tenant.from-token fuellt.
        */
        Route::middleware('ability:'.TenantApiAbility::FUNNELS_READ->value)
            ->scopeBindings()
            ->group(function () {
                Route::get('/funnels', [FunnelController::class, 'index'])->name('funnels.index');
                Route::get('/funnels/{funnel:public_token}', [FunnelController::class, 'show'])->name('funnels.show');
                Route::get('/funnels/{funnel:public_token}/structure', [ManagementFunnelStructureController::class, 'show'])->name('funnels.structure.show');

                Route::get('/funnels/{funnel:public_token}/steps', [FunnelStepController::class, 'index'])->name('steps.index');
                Route::get('/funnels/{funnel:public_token}/steps/{step}', [FunnelStepController::class, 'show'])->name('steps.show');
                Route::get('/funnels/{funnel:public_token}/steps/{step}/questions', [FunnelQuestionController::class, 'index'])->name('questions.index');
                Route::get('/funnels/{funnel:public_token}/steps/{step}/questions/{question}', [FunnelQuestionController::class, 'show'])->name('questions.show');
                Route::get('/funnels/{funnel:public_token}/steps/{step}/questions/{question}/options', [FunnelOptionController::class, 'index'])->name('options.index');
                Route::get('/funnels/{funnel:public_token}/steps/{step}/questions/{question}/options/{option}', [FunnelOptionController::class, 'show'])->name('options.show');

                Route::get('/funnels/{funnel:public_token}/conditions', [FunnelConditionController::class, 'index'])->name('conditions.index');
                Route::get('/funnels/{funnel:public_token}/conditions/{condition}', [FunnelConditionController::class, 'show'])->name('conditions.show');

                Route::get('/funnels/{funnel:public_token}/results', [FunnelResultController::class, 'index'])->name('results.index');
                Route::get('/funnels/{funnel:public_token}/results/{result}', [FunnelResultController::class, 'show'])->name('results.show');

                // FB-030c: Versionshistorie -- lesend, deshalb funnels:read.
                Route::get('/funnels/{funnel:public_token}/versions', [FunnelVersionController::class, 'index'])->name('versions.index');
                Route::get('/funnels/{funnel:public_token}/versions/{version}', [FunnelVersionController::class, 'show'])
                    ->whereNumber('version')
                    ->name('versions.show');
            });

        Route::middleware('ability:'.TenantApiAbility::FUNNELS_WRITE->value)
            ->scopeBindings()
            ->group(function () {
                Route::post('/funnels', [FunnelController::class, 'store'])->name('funnels.store');
                Route::patch('/funnels/{funnel:public_token}', [FunnelController::class, 'update'])->name('funnels.update');
                Route::delete('/funnels/{funnel:public_token}', [FunnelController::class, 'destroy'])->name('funnels.destroy');
                Route::put('/funnels/{funnel:public_token}/structure', [ManagementFunnelStructureController::class, 'update'])->name('funnels.structure.update');

                Route::post('/funnels/{funnel:public_token}/steps', [FunnelStepController::class, 'store'])->name('steps.store');
                Route::patch('/funnels/{funnel:public_token}/steps/{step}', [FunnelStepController::class, 'update'])->name('steps.update');
                Route::delete('/funnels/{funnel:public_token}/steps/{step}', [FunnelStepController::class, 'destroy'])->name('steps.destroy');

                Route::post('/funnels/{funnel:public_token}/steps/{step}/questions', [FunnelQuestionController::class, 'store'])->name('questions.store');
                Route::patch('/funnels/{funnel:public_token}/steps/{step}/questions/{question}', [FunnelQuestionController::class, 'update'])->name('questions.update');
                Route::delete('/funnels/{funnel:public_token}/steps/{step}/questions/{question}', [FunnelQuestionController::class, 'destroy'])->name('questions.destroy');

                Route::post('/funnels/{funnel:public_token}/steps/{step}/questions/{question}/options', [FunnelOptionController::class, 'store'])->name('options.store');
                Route::patch('/funnels/{funnel:public_token}/steps/{step}/questions/{question}/options/{option}', [FunnelOptionController::class, 'update'])->name('options.update');
                Route::delete('/funnels/{funnel:public_token}/steps/{step}/questions/{question}/options/{option}', [FunnelOptionController::class, 'destroy'])->name('options.destroy');

                Route::post('/funnels/{funnel:public_token}/conditions', [FunnelConditionController::class, 'store'])->name('conditions.store');
                Route::patch('/funnels/{funnel:public_token}/conditions/{condition}', [FunnelConditionController::class, 'update'])->name('conditions.update');
                Route::delete('/funnels/{funnel:public_token}/conditions/{condition}', [FunnelConditionController::class, 'destroy'])->name('conditions.destroy');

                Route::post('/funnels/{funnel:public_token}/results', [FunnelResultController::class, 'store'])->name('results.store');
                Route::patch('/funnels/{funnel:public_token}/results/{result}', [FunnelResultController::class, 'update'])->name('results.update');
                Route::delete('/funnels/{funnel:public_token}/results/{result}', [FunnelResultController::class, 'destroy'])->name('results.destroy');

                // FB-030c: Lebenszyklus. Ruft die Actions aus FB-014 und FB-018.
                Route::post('/funnels/{funnel:public_token}/publish', [FunnelLifecycleController::class, 'publish'])->name('funnels.publish');
                Route::post('/funnels/{funnel:public_token}/duplicate', [FunnelLifecycleController::class, 'duplicate'])->name('funnels.duplicate');
                Route::post('/funnels/{funnel:public_token}/archive', [FunnelLifecycleController::class, 'archive'])->name('funnels.archive');

            });

        /*
        | FB-030e: Webhooks. Eigene Berechtigung, weil ein Webhook Kontaktdaten
        | nach draussen traegt -- wer Funnels bearbeiten darf, darf deshalb nicht
        | automatisch Ereignisse umleiten.
        */
        Route::middleware('ability:'.TenantApiAbility::WEBHOOKS_MANAGE->value)
            ->scopeBindings()
            ->group(function () {
                Route::get('/funnels/{funnel:public_token}/webhooks', [FunnelWebhookController::class, 'index'])->name('webhooks.index');
                Route::post('/funnels/{funnel:public_token}/webhooks', [FunnelWebhookController::class, 'store'])->name('webhooks.store');
                Route::get('/funnels/{funnel:public_token}/webhooks/{webhook}', [FunnelWebhookController::class, 'show'])->name('webhooks.show');
                Route::patch('/funnels/{funnel:public_token}/webhooks/{webhook}', [FunnelWebhookController::class, 'update'])->name('webhooks.update');
                Route::delete('/funnels/{funnel:public_token}/webhooks/{webhook}', [FunnelWebhookController::class, 'destroy'])->name('webhooks.destroy');
                Route::get('/funnels/{funnel:public_token}/webhooks/{webhook}/deliveries', [WebhookDeliveryController::class, 'index'])->name('webhooks.deliveries.index');
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
