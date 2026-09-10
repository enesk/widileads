<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Actions\CreateLeadFromSession;
use App\Actions\PublishFunnel;
use App\Constants\LeadContactStatus;
use App\Constants\TenantType;
use App\Dto\FunnelSubmissionData;
use App\Marketplace\MatchableLead;
use App\Models\Lead;
use App\Models\PublicSession;
use App\Models\Scopes\TenantScopes;
use App\Models\Tenant;
use App\Models\User;
use App\Presenters\LeadPresenter;
use App\Services\FunnelTemplateImporter;
use App\Services\LeadPurchaseLookup;
use Illuminate\Support\Facades\Blade;
use Tests\Feature\FeatureTest;

/**
 * FB-032: Kontaktdaten halten vor dem Kauf dicht.
 *
 * Das ist die Zusage, an der die Plattform haengt: Ein Kaeufer sieht vor dem
 * Kauf, ob ein Lead zu ihm passt -- aber nicht, wen er anrufen koennte.
 * Geprueft wird deshalb nicht das Feld, sondern das gesamte gerenderte
 * Dokument.
 */
class LeadContactMaskingTest extends FeatureTest
{
    private const EMAIL = 'mara.lindqvist@example.com';

    private const PHONE = '+493012345678';

    private const POSTAL_CODE = '76131';

    /**
     * @var array<string, mixed>
     */
    private const ANSWERS = [
        'tierart' => 'hund',
        'alter' => 'ab_8',
        'vorerkrankungen' => ['gelenke'],
        'vorname' => 'Mara',
        'nachname' => 'Lindqvist',
        'email' => self::EMAIL,
        'telefon' => self::PHONE,
        'plz' => self::POSTAL_CODE,
        'einwilligung' => true,
    ];

    private function lead(): Lead
    {
        $operator = Tenant::factory()->create(['type' => TenantType::OPERATOR]);
        $funnel = app(FunnelTemplateImporter::class)->import('pfotencheck', $operator);
        $version = app(PublishFunnel::class)->handle($funnel);

        $session = PublicSession::factory()->completed()->create([
            'funnel_version_id' => $version->id,
            'answers' => self::ANSWERS,
        ]);

        return app(CreateLeadFromSession::class)->handle(new FunnelSubmissionData(
            publicToken: (string) $funnel->public_token,
            funnelVersionId: (int) $version->id,
            publicSessionId: (int) $session->id,
            answers: self::ANSWERS,
            score: 8,
            resultKey: '8-20',
        ));
    }

    private function buyer(): User
    {
        $tenant = Tenant::factory()->create(['type' => TenantType::BUYER]);

        return $this->createUser($tenant);
    }

    private function renderDetailFor(Lead $lead, ?User $viewer): string
    {
        return Blade::render(
            '<x-lead.contact-card :presenter="$presenter" />',
            ['presenter' => new LeadPresenter($lead, $viewer)],
        );
    }

    public function test_the_rendered_detail_contains_no_clear_text_contact_for_a_buyer_without_purchase(): void
    {
        $lead = $this->lead();

        $html = $this->renderDetailFor($lead, $this->buyer());

        // Die Ticketzusage: nicht im Feld, sondern im GESAMTEN Dokument.
        $this->assertStringNotContainsString(self::EMAIL, $html);
        $this->assertStringNotContainsString(self::PHONE, $html);
        $this->assertStringNotContainsString('3012345678', $html, 'Auch nicht ohne Landesvorwahl.');
        $this->assertStringNotContainsString(self::POSTAL_CODE, $html);

        // Was der Kaeufer sehen soll: genug, um den Lead einzuschaetzen.
        $this->assertStringContainsString('m…@example.com', $html);
        $this->assertStringContainsString('+49 30 …', $html);
        $this->assertStringContainsString('76…', $html);
        $this->assertStringContainsString('Mara Lindqvist', $html, 'Der Name bleibt im Klartext.');
    }

    public function test_each_field_is_masked_the_way_the_ticket_describes(): void
    {
        $contact = $this->lead()->contactFor(null);

        $this->assertTrue($contact->masked);
        $this->assertSame('m…@example.com', $contact->email);
        $this->assertSame('+49 30 …', $contact->phone);
        $this->assertSame('76…', $contact->postalCode);
        $this->assertSame('Mara', $contact->firstName);
        $this->assertSame('Lindqvist', $contact->lastName);
    }

    public function test_the_owning_operator_and_a_buyer_after_purchase_see_clear_text(): void
    {
        $lead = $this->lead();

        // Der Betreiber hat die Daten selbst erhoben.
        $operatorUser = $this->createUser(Tenant::query()->findOrFail($lead->tenant_id));
        $operatorContact = $lead->contactFor($operatorUser);

        $this->assertFalse($operatorContact->masked);
        $this->assertSame(self::EMAIL, $operatorContact->email);
        $this->assertSame(self::PHONE, $operatorContact->phone);

        // Und der Kaeufer, sobald er gekauft hat -- mit einer Ausnahme: Die
        // Rufnummer bleibt nach dem Kauf verdeckt, bis die
        // Erreichbarkeitspruefung mit `billable` geendet hat (FB-085).
        $buyer = $this->buyer();
        $this->assumeEveryTenantHasPurchased();

        $html = $this->renderDetailFor($lead, $buyer);

        $this->assertStringContainsString(self::EMAIL, $html);
        $this->assertStringContainsString(self::POSTAL_CODE, $html);

        $this->assertStringNotContainsString(self::PHONE, $html);
        $this->assertStringNotContainsString('3012345678', $html, 'Auch nicht ohne Landesvorwahl.');
        $this->assertStringContainsString((string) $lead->maskedPhone(), $html);
    }

    public function test_a_buyer_sees_the_clear_text_phone_once_the_lead_is_billable(): void
    {
        $lead = $this->lead();
        $buyer = $this->buyer();
        $this->assumeEveryTenantHasPurchased();

        // FB-085: Erst der Abschluss der Erreichbarkeitspruefung gibt die
        // Nummer frei. Gesetzt wird der Stand sonst vom LeadResolver.
        Lead::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->whereKey($lead->getKey())
            ->update([
                'contact_status' => LeadContactStatus::BILLABLE->value,
                'resolved_at' => now(),
            ]);

        $lead->setAttribute('contact_status', LeadContactStatus::BILLABLE);

        $html = $this->renderDetailFor($lead, $buyer);

        $this->assertStringContainsString(self::PHONE, $html);
        $this->assertStringContainsString(self::EMAIL, $html);
        $this->assertStringContainsString(self::POSTAL_CODE, $html);
    }

    public function test_the_marketplace_keeps_filtering_on_clear_text_values(): void
    {
        $lead = $this->lead();

        // FB-051 filtert serverseitig auf echten Werten. Die Maskierung greift
        // bei der Ausgabe an den Kaeufer -- sie darf die Filterung nicht
        // mitnehmen, sonst passte kein Postleitzahl-Kriterium mehr.
        $matchable = MatchableLead::fromLead($lead->fresh(['answers']));

        $this->assertSame(self::POSTAL_CODE, $matchable->postalCode);
    }

    /**
     * Den Kauf selbst baut FB-054; hier steht nur seine Zusage.
     */
    private function assumeEveryTenantHasPurchased(): void
    {
        $this->app->bind(LeadPurchaseLookup::class, fn () => new class implements LeadPurchaseLookup
        {
            public function hasPurchased(Tenant $tenant, Lead $lead): bool
            {
                return true;
            }
        });
    }
}
