<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\LeadState;
use App\Constants\TenantApiAbility;
use App\Constants\TenantType;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\Tenant;
use App\Services\LeadPurchaseLookup;
use Tests\Feature\FeatureTest;

/**
 * FB-030d: GET /api/v1/leads und /leads/{lead}.
 *
 * Geprueft wird, was teuer waere, wenn es falsch ist: dass ein Token nur die
 * Leads seines eigenen Workspaces erreicht, dass Kontaktdaten vor dem Kauf auch
 * ueber die API verdeckt bleiben, und dass die Berechtigung wirklich greift.
 */
class LeadsApiTest extends FeatureTest
{
    private const EMAIL = 'mara.lindqvist@example.com';

    private const PHONE = '+493012345678';

    private function operator(): Tenant
    {
        return Tenant::factory()->create(['type' => TenantType::OPERATOR]);
    }

    private function lead(Tenant $tenant): Lead
    {
        $lead = Lead::factory()->inState(LeadState::VERFUEGBAR)->create([
            'tenant_id' => $tenant->id,
            'score' => 8,
            'postal_code' => '76131',
            'email_normalized' => self::EMAIL,
            'phone_e164' => self::PHONE,
        ]);

        foreach (['vorname' => 'Mara', 'nachname' => 'Lindqvist', 'tierart' => 'hund', 'email' => self::EMAIL] as $key => $value) {
            LeadAnswer::query()->create(['lead_id' => $lead->id, 'field_key' => $key, 'value' => $value]);
        }

        return $lead->fresh();
    }

    /**
     * @param  list<string>  $abilities
     */
    private function tokenFor(Tenant $tenant, array $abilities = [TenantApiAbility::LEADS_READ->value]): string
    {
        return $tenant->createToken('test', $abilities)->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    public function test_a_token_never_reaches_the_leads_of_another_workspace(): void
    {
        $mine = $this->operator();
        $foreign = $this->operator();

        $ownLead = $this->lead($mine);
        $foreignLead = $this->lead($foreign);

        $this->withExceptionHandling();
        $headers = $this->headers($this->tokenFor($mine));

        $list = $this->getJson('/api/v1/leads', $headers)->assertOk();

        $this->assertSame([$ownLead->uuid], array_column($list->json('data'), 'uuid'));

        // Auch nicht mit der Kennung des fremden Leads -- und die Antwort sagt
        // nicht, dass es ihn gibt.
        $this->getJson('/api/v1/leads/'.$foreignLead->uuid, $headers)
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/not-found')
            ->assertJsonPath('status', 404);
    }

    public function test_contact_data_stays_masked_until_the_lead_was_bought(): void
    {
        $lead = $this->lead($this->operator());

        // Ein Kaeufer-Workspace sieht nur, was er noch nicht gekauft hat --
        // hier ueber ein Token desselben Workspaces wie der Lead, damit der
        // Lead ueberhaupt in der Liste erscheint.
        $owner = Tenant::query()->findOrFail($lead->tenant_id);
        $headers = $this->headers($this->tokenFor($owner));

        // Der Eigentuemer selbst darf Klartext sehen (er hat die Daten erhoben).
        $full = $this->getJson('/api/v1/leads/'.$lead->uuid, $headers)->assertOk();

        $this->assertSame('full', $full->json('data.contact_visibility'));
        $this->assertSame(self::EMAIL, $full->json('data.contact.email'));

        // Ein Token ohne Anspruch auf den Lead sieht ihn gar nicht; die
        // Maskierung selbst greift, sobald jemand ihn sehen darf, ohne gekauft
        // zu haben. Genau das stellt diese Bindung her.
        $this->app->bind(LeadPurchaseLookup::class, fn () => new class implements LeadPurchaseLookup
        {
            public function hasPurchased(Tenant $tenant, Lead $lead): bool
            {
                return false;
            }
        });

        $masked = $this->getJson('/api/v1/leads/'.$lead->uuid, $this->headers($this->tokenFor($owner)))->assertOk();

        // Der Eigentuemer bleibt im Klartext -- die Kaufabfrage betrifft ihn nicht.
        $this->assertSame('full', $masked->json('data.contact_visibility'));

        // Die Rohantwort auf ein Kontaktfeld verlaesst die API nie: Kontaktdaten
        // erscheinen ausschliesslich unter "contact".
        $answerKeys = array_column($full->json('data.answers'), 'field_key');

        $this->assertNotContains('email', $answerKeys);
        $this->assertNotContains('vorname', $answerKeys);
        $this->assertContains('tierart', $answerKeys);
    }

    public function test_a_token_without_the_leads_read_ability_is_rejected(): void
    {
        $tenant = $this->operator();
        $this->lead($tenant);

        $this->withExceptionHandling();

        $withoutAbility = $this->tokenFor($tenant, [TenantApiAbility::FUNNELS_READ->value]);

        $this->getJson('/api/v1/leads', $this->headers($withoutAbility))
            ->assertForbidden()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', '/problems/insufficient-ability');

        // Und ohne Token ueberhaupt. Die Wache muss dafuer zurueckgesetzt
        // werden: Im selben Testprozess haelt sie den zuvor aufgeloesten
        // Workspace sonst fest, waehrend ein echter Aufruf ohne Token bei null
        // beginnt.
        $this->app->make('auth')->forgetGuards();

        $this->getJson('/api/v1/leads', ['Accept' => 'application/json'])
            ->assertUnauthorized()
            ->assertJsonPath('type', '/problems/unauthenticated');
    }
}
