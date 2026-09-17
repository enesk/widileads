<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\TenantType;
use App\Constants\WebhookEvent;
use App\Events\Lead\LeadCreated;
use App\Jobs\DeliverWebhook;
use App\Listeners\Webhooks\DispatchLeadWebhooks;
use App\Models\Funnel;
use App\Models\FunnelWebhook;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Services\WebhookSigner;
use Database\Seeders\ElektrikerportalFunnelSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Feature\FeatureTest;

/**
 * `lead.created` fuer die SUN-Portale.
 *
 * Das Portal ordnet eine Anfrage ueber die Antwort `firmenprofil` dem Betrieb
 * zu und erkennt Wiederholungen an der Lead-UUID. Fehlt eins von beiden,
 * verwirft es den Lead -- ohne dass bei uns etwas fehlschlaegt.
 */
class LeadCreatedWebhookPayloadTest extends FeatureTest
{
    private const FIRMENPROFIL = '{"id":17,"slug":"elektro-polz","name":"Elektro Polz","url":"https://elektrikerportal.com/elektro-polz","portal":"elektrikerportal"}';

    public function test_lead_created_carries_uuid_and_answers_and_a_verifiable_signature(): void
    {
        Http::fake(['*' => Http::response('', 202)]);

        Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $this->seed(ElektrikerportalFunnelSeeder::class);

        $funnel = Funnel::query()->withoutGlobalScopes()->where('slug', 'elektrikerportal-anfrage')->sole();
        $webhook = FunnelWebhook::query()->where('funnel_id', $funnel->id)->sole();

        $this->assertSame('https://elektrikerportal.com/webhooks/leads', $webhook->url);
        $this->assertSame([WebhookEvent::LEAD_CREATED->value], $webhook->events);
        $this->assertTrue($webhook->active);
        $this->assertGreaterThanOrEqual(32, strlen($webhook->secret));

        $lead = Lead::factory()->create([
            'tenant_id' => $funnel->tenant_id,
            'funnel_id' => $funnel->id,
            'funnel_version_id' => $funnel->versions()->latest('id')->value('id'),
            'email_normalized' => 'kunde@example.com',
        ]);

        foreach ([
            'leistung' => 'reparatur',
            'firmenprofil' => self::FIRMENPROFIL,
            'erreichbar' => ['vormittags', 'ab_17_uhr'],
            'name' => 'Kim Kunde',
            'telefon' => '+4915112345678',
            'email' => 'kunde@example.com',
            'plz' => '76131',
        ] as $key => $value) {
            LeadAnswer::query()->create(['lead_id' => $lead->id, 'field_key' => $key, 'value' => $value]);
        }

        app(DispatchLeadWebhooks::class)->handleLeadCreated(new LeadCreated($lead->fresh()));

        $delivery = WebhookDelivery::query()->sole();
        $payload = $delivery->payload['data']['lead'];
        $answers = collect($payload['answers'])->keyBy('field_key');

        $this->assertSame($lead->uuid, $payload['uuid']);

        // Die Kennung des Betriebs ist keine persoenliche Angabe und muss mit.
        $this->assertSame(self::FIRMENPROFIL, $answers['firmenprofil']['value']);
        $this->assertSame('Firmenprofil', $answers['firmenprofil']['label']);

        $this->assertSame('Leistung', $answers['leistung']['label']);
        $this->assertSame('reparatur', $answers['leistung']['value']);
        $this->assertSame('Reparatur / Störung', $answers['leistung']['value_label']);
        $this->assertSame('Vormittags, Ab 17 Uhr', $answers['erreichbar']['value_label']);

        // Kontaktfelder stehen nur unter "contact", nie in den Rohantworten.
        foreach (['name', 'telefon', 'email', 'plz'] as $personal) {
            $this->assertArrayNotHasKey($personal, $answers->all());
        }

        $this->assertSame('kunde@example.com', $payload['contact']['email']);

        app(DeliverWebhook::class, ['deliveryId' => $delivery->id])->handle(app(WebhookSigner::class));

        $signer = app(WebhookSigner::class);
        $secret = $webhook->refresh()->secret;

        Http::assertSent(function ($request) use ($signer, $secret, $lead): bool {
            $this->assertSame(WebhookEvent::LEAD_CREATED->value, $request->header(WebhookSigner::EVENT_HEADER)[0] ?? null);
            $this->assertTrue($signer->verify(
                $secret,
                $request->body(),
                (int) ($request->header(WebhookSigner::TIMESTAMP_HEADER)[0] ?? 0),
                $request->header(WebhookSigner::SIGNATURE_HEADER)[0] ?? '',
            ));
            $this->assertSame($lead->uuid, $request->data()['data']['lead']['uuid']);

            return true;
        });

        $this->assertSame(202, $delivery->refresh()->response_code);
    }

    public function test_a_second_seeder_run_keeps_the_secret(): void
    {
        Tenant::factory()->create(['type' => TenantType::OPERATOR]);

        $this->seed(ElektrikerportalFunnelSeeder::class);
        $secret = FunnelWebhook::query()->sole()->secret;

        $this->seed(ElektrikerportalFunnelSeeder::class);

        $this->assertSame($secret, FunnelWebhook::query()->sole()->secret);
    }
}
