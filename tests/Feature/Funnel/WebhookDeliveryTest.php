<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\WebhookDeliveryStatus;
use App\Constants\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Funnel;
use App\Models\FunnelWebhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use App\Services\WebhookSigner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\FeatureTest;

/**
 * FB-030e: Signatur und Zustellung von Webhooks.
 *
 * Die Signatur ist der einzige Grund, warum ein Empfaenger unserem Aufruf
 * glauben darf. Waere sie falsch gebildet oder liesse sie sich wiedereinspielen,
 * koennte jeder Ereignisse erfinden -- etwa einen Lead, den es nie gab, oder
 * einen Zustandswechsel, der nie stattfand.
 */
class WebhookDeliveryTest extends FeatureTest
{
    public function test_a_receiver_can_verify_the_signature_and_a_manipulated_one_fails(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $webhook = FunnelWebhook::factory()->create(['events' => [WebhookEvent::FUNNEL_PUBLISHED->value]]);

        app(WebhookDispatcher::class)->dispatch(
            $webhook->funnel,
            WebhookEvent::FUNNEL_PUBLISHED,
            ['version' => 1],
        );

        $delivery = WebhookDelivery::query()->sole();

        app(DeliverWebhook::class, ['deliveryId' => $delivery->getKey()])->handle(app(WebhookSigner::class));

        $signer = app(WebhookSigner::class);
        $secret = $webhook->refresh()->secret;

        Http::assertSent(function ($request) use ($signer, $secret): bool {
            $signature = $request->header(WebhookSigner::SIGNATURE_HEADER)[0] ?? '';
            $timestamp = (int) ($request->header(WebhookSigner::TIMESTAMP_HEADER)[0] ?? 0);
            $payload = $request->body();

            // So prueft ein Empfaenger: Rumpf und Zeitstempel mit dem eigenen
            // Secret nachrechnen.
            $this->assertTrue(
                $signer->verify($secret, $payload, $timestamp, $signature),
                'Der Empfaenger muss die Signatur mit seinem Secret nachrechnen koennen.',
            );

            // Manipulierter Rumpf: Die Signatur passt nicht mehr.
            $this->assertFalse(
                $signer->verify($secret, $payload.'x', $timestamp, $signature),
                'Ein veraenderter Rumpf darf die Signatur nicht mehr erfuellen.',
            );

            // Wiedereinspielen mit anderem Zeitstempel: ebenfalls ungueltig.
            $this->assertFalse(
                $signer->verify($secret, $payload, $timestamp + 1, $signature),
                'Ohne den Zeitstempel im Hash liesse sich eine Zustellung beliebig wiederholen.',
            );

            // Fremdes Secret: ungueltig.
            $this->assertFalse($signer->verify(FunnelWebhook::newSecret(), $payload, $timestamp, $signature));

            return true;
        });

        $this->assertSame(WebhookDeliveryStatus::DELIVERED, $delivery->refresh()->status);
        $this->assertSame(200, $delivery->response_code);
        $this->assertSame(1, $delivery->attempts);
    }

    public function test_a_failing_receiver_is_retried_and_finally_given_up_on(): void
    {
        config()->set('funnel.webhooks.max_attempts', 2);
        Http::fake(['*' => Http::response('', 500)]);

        // Ohne das liefe der eingeplante Folgeversuch in der Testumgebung sofort
        // mit (Queue laeuft synchron) -- und der Zwischenzustand waere nicht
        // pruefbar.
        Queue::fake();

        $webhook = FunnelWebhook::factory()->create(['events' => [WebhookEvent::FUNNEL_PUBLISHED->value]]);

        app(WebhookDispatcher::class)->dispatch($webhook->funnel, WebhookEvent::FUNNEL_PUBLISHED, []);

        $delivery = WebhookDelivery::query()->sole();
        $signer = app(WebhookSigner::class);

        app(DeliverWebhook::class, ['deliveryId' => $delivery->getKey()])->handle($signer);

        $delivery->refresh();

        $this->assertSame(WebhookDeliveryStatus::PENDING, $delivery->status);
        $this->assertSame(500, $delivery->response_code);
        $this->assertNotNull($delivery->next_attempt_at, 'Ein weiterer Versuch muss eingeplant sein.');
        Queue::assertPushed(DeliverWebhook::class);

        app(DeliverWebhook::class, ['deliveryId' => $delivery->getKey()])->handle($signer);

        $delivery->refresh();

        // Nach dem letzten Versuch hoert die Zustellung auf -- ein dauerhaft
        // nicht erreichbarer Empfaenger darf die Queue nicht fuellen.
        $this->assertSame(WebhookDeliveryStatus::EXHAUSTED, $delivery->status);
        $this->assertSame(2, $delivery->attempts);
        $this->assertNull($delivery->next_attempt_at);
    }

    public function test_the_event_id_stays_stable_so_a_receiver_recognises_repetitions(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $webhook = FunnelWebhook::factory()->create(['events' => [WebhookEvent::FUNNEL_PUBLISHED->value]]);

        app(WebhookDispatcher::class)->dispatch($webhook->funnel, WebhookEvent::FUNNEL_PUBLISHED, []);

        $delivery = WebhookDelivery::query()->sole();
        $firstPayloadId = $delivery->payload['id'];

        app(DeliverWebhook::class, ['deliveryId' => $delivery->getKey()])->handle(app(WebhookSigner::class));

        $this->assertSame($firstPayloadId, $delivery->refresh()->payload['id']);
    }

    public function test_a_webhook_only_receives_the_events_it_subscribed_to(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $funnel = Funnel::factory()->create();
        FunnelWebhook::factory()->create([
            'funnel_id' => $funnel->id,
            'events' => [WebhookEvent::LEAD_PURCHASED->value],
        ]);

        app(WebhookDispatcher::class)->dispatch($funnel, WebhookEvent::FUNNEL_PUBLISHED, []);

        $this->assertSame(0, WebhookDelivery::query()->count());

        app(WebhookDispatcher::class)->dispatch($funnel, WebhookEvent::LEAD_PURCHASED, []);

        $this->assertSame(1, WebhookDelivery::query()->count());
    }
}
