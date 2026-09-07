<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\FunnelStatus;
use App\Constants\LeadState;
use App\Constants\TenantType;
use App\Filament\Dashboard\Pages\Marketplace;
use App\Marketplace\MarketplaceListing;
use App\Models\BuyerProfile;
use App\Models\BuyerRegistration;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\LeadWatchlistEntry;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * FB-053: Der Marktplatz zeigt einem Kaeufer genau das, was ihn angeht -- und
 * nicht mehr.
 *
 * Zwei Zusagen stehen hier auf dem Spiel. Erstens die Mandantentrennung: Der
 * Marktplatz ist die zweite Stelle, an der der Mandanten-Scope bewusst
 * abgeschaltet wird, also muss belegt sein, was dabei herauskommt. Zweitens die
 * Maskierung: Ein Kaeufer soll einschaetzen koennen, ob ein Lead zu ihm passt,
 * aber nicht, wen er anrufen koennte -- geprueft am gesamten gerenderten
 * Dokument, nicht am einzelnen Feld.
 */
class MarketplaceListingTest extends FeatureTest
{
    private const EMAIL = 'mara.lindqvist@example.com';

    private const PHONE = '+493012345678';

    private const POSTAL_CODE = '76131';

    private function operator(): Tenant
    {
        return Tenant::factory()->create(['type' => TenantType::OPERATOR]);
    }

    private function funnelOf(Tenant $tenant, string $name = 'Pfotencheck'): Funnel
    {
        return Funnel::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'status' => FunnelStatus::PUBLISHED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $answers
     */
    private function lead(
        Tenant $owner,
        ?Funnel $funnel = null,
        array $attributes = [],
        array $answers = [],
        LeadState $state = LeadState::VERFUEGBAR,
    ): Lead {
        $lead = Lead::factory()->inState($state)->create(array_merge([
            'tenant_id' => $owner->getKey(),
            'funnel_id' => $funnel?->getKey(),
            'score' => 10,
            'email_normalized' => self::EMAIL,
            'phone_e164' => self::PHONE,
        ], $attributes));

        $answers = array_merge([
            'tierart' => 'hund',
            'vorname' => 'Mara',
            'nachname' => 'Lindqvist',
            'email' => self::EMAIL,
            'telefon' => self::PHONE,
            'plz' => self::POSTAL_CODE,
        ], $answers);

        foreach ($answers as $fieldKey => $value) {
            LeadAnswer::query()->create([
                'lead_id' => $lead->getKey(),
                'field_key' => $fieldKey,
                'value' => $value,
            ]);
        }

        return $lead->fresh(['answers']);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{Tenant, User}
     */
    private function approvedBuyer(array $criteria = []): array
    {
        $registration = BuyerRegistration::factory()->approved()->create();
        $tenant = $registration->tenant->fresh();

        $user = User::factory()->create();
        $tenant->users()->attach($user);

        if ($criteria !== []) {
            BuyerProfile::query()->create(array_merge(['tenant_id' => $tenant->getKey()], $criteria));
        }

        return [$tenant, $user];
    }

    public function test_a_buyer_only_sees_leads_that_pass_the_profile(): void
    {
        $operator = $this->operator();
        $wanted = $this->funnelOf($operator, 'Pfotencheck');
        $unwanted = $this->funnelOf($operator, 'Zahnvorsorge');

        $matching = $this->lead($operator, $wanted, ['score' => 12]);

        // Deckungsgleichheit von SQL-Vorauswahl und Matcher: Die Datenbank
        // schraenkt die Region ueber ein LIKE auf dem getrimmten Wert vor, der
        // Matcher vergleicht mit trim() und str_starts_with(). Ein Wert mit
        // Leerraum muss deshalb durch beide Pruefungen kommen -- schluckte die
        // Vorauswahl ihn, saehe der Kaeufer einen passenden Lead nie, und es
        // faellt niemandem auf.
        $paddedPostalCode = $this->lead($operator, $wanted, ['score' => 12], ['plz' => ' '.self::POSTAL_CODE.' ']);

        $wrongFunnel = $this->lead($operator, $unwanted, ['score' => 12]);
        $wrongRegion = $this->lead($operator, $wanted, ['score' => 12], ['plz' => '10115']);
        $tooLowScore = $this->lead($operator, $wanted, ['score' => 3]);
        $wrongAnswer = $this->lead($operator, $wanted, ['score' => 12], ['tierart' => 'pferd']);

        [$tenant] = $this->approvedBuyer([
            'funnel_ids' => [$wanted->getKey()],
            'postal_prefixes' => ['76'],
            'answer_filters' => ['tierart' => ['hund', 'katze']],
            'min_score' => 10,
        ]);

        $visible = app(MarketplaceListing::class)
            ->for($tenant, BuyerProfile::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->getKey())->first())
            ->pluck('id')
            ->all();

        sort($visible);
        $expected = [$matching->getKey(), $paddedPostalCode->getKey()];
        sort($expected);

        $this->assertSame($expected, $visible);

        foreach ([$wrongFunnel, $wrongRegion, $tooLowScore, $wrongAnswer] as $hidden) {
            $this->assertNotContains($hidden->getKey(), $visible);
        }
    }

    public function test_leads_of_other_states_or_of_non_operator_tenants_never_appear(): void
    {
        $operator = $this->operator();
        $funnel = $this->funnelOf($operator);

        $available = $this->lead($operator, $funnel);
        $reserved = $this->lead($operator, $funnel, [], [], LeadState::RESERVIERT);

        $hidden = [
            $this->lead($operator, $funnel, [], [], LeadState::NEU),
            $this->lead($operator, $funnel, [], [], LeadState::VERKAUFT),
            $this->lead($operator, $funnel, [], [], LeadState::UNGUELTIG),
            $this->lead($operator, $funnel, [], [], LeadState::ABGELAUFEN),
            // Anonymisiert nach Ablauf der Aufbewahrungsfrist (FB-037) -- ohne
            // Personenbezug ist ein Lead nichts mehr wert.
            $this->lead($operator, $funnel, ['anonymized_at' => now()]),
        ];

        // Legt ein Kaeufer-Mandant selbst einen Funnel an, landen dessen Leads
        // nie im Marktplatz -- dieselbe Grenze wie im Funnel-Katalog (FB-051).
        $foreignBuyer = Tenant::factory()->create(['type' => TenantType::BUYER]);
        $hidden[] = $this->lead($foreignBuyer, $this->funnelOf($foreignBuyer, 'Fremdangebot'));

        [$tenant] = $this->approvedBuyer();

        $visible = app(MarketplaceListing::class)->for($tenant, null)->pluck('id')->all();

        sort($visible);
        $expected = [$available->getKey(), $reserved->getKey()];
        sort($expected);

        $this->assertSame($expected, $visible);

        foreach ($hidden as $lead) {
            $this->assertNotContains($lead->getKey(), $visible);
        }
    }

    public function test_the_rendered_marketplace_contains_no_clear_text_contact_data(): void
    {
        $operator = $this->operator();
        $funnel = $this->funnelOf($operator);
        $this->lead($operator, $funnel, [], [], LeadState::RESERVIERT);
        $this->lead($operator, $funnel);

        [$tenant, $user] = $this->approvedBuyer();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);

        $html = Livewire::actingAs($user)->test(Marketplace::class)->assertSuccessful()->html();

        // Der Name bleibt lesbar -- er allein macht niemanden erreichbar und ist
        // die Angabe, an der ein Kaeufer einen Lead wiedererkennt.
        $this->assertStringContainsString('Mara', $html);

        // Alles Kontaktierbare nicht, und zwar im gesamten Dokument. Auch die
        // Antwort auf den Feldschluessel `plz` darf nicht als Qualifizierungs-
        // angabe durchrutschen: lead_answers traegt die Kontaktfelder mit.
        $this->assertStringNotContainsString(self::EMAIL, $html);
        $this->assertStringNotContainsString(self::PHONE, $html);
        $this->assertStringNotContainsString(self::POSTAL_CODE, $html);
        $this->assertStringNotContainsString('mara.lindqvist', $html);

        // Die verdeckten Fassungen sind da -- der Kaeufer sieht die Region.
        $this->assertStringContainsString('76…', $html);

        // Qualifizierungsdaten dagegen im Klartext, samt Zustandshinweis.
        $this->assertStringContainsString('tierart', $html);
        $this->assertStringContainsString('hund', $html);
        $this->assertStringContainsString(__('marketplace.listing.taken'), $html);
    }

    public function test_the_watchlist_belongs_to_the_buyer_who_created_it(): void
    {
        $operator = $this->operator();
        $funnel = $this->funnelOf($operator);
        $lead = $this->lead($operator, $funnel);

        [$tenant, $user] = $this->approvedBuyer();
        [$otherTenant] = $this->approvedBuyer();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($tenant);

        Livewire::actingAs($user)->test(Marketplace::class)
            ->callAction(TestAction::make('watch')->table($lead))
            ->assertSuccessful();

        $entries = LeadWatchlistEntry::query()->withoutGlobalScope('tenant')->get();

        $this->assertCount(1, $entries);
        $this->assertSame($tenant->getKey(), $entries->first()->tenant_id);

        // Der andere Kaeufer sieht die Vormerkung nicht.
        $this->assertSame(
            0,
            LeadWatchlistEntry::query()->withoutGlobalScope('tenant')
                ->where('tenant_id', $otherTenant->getKey())->count(),
        );

        // Und ein zweiter Aufruf nimmt sie wieder zurueck.
        Livewire::actingAs($user)->test(Marketplace::class)
            ->callAction(TestAction::make('watch')->table($lead));

        $this->assertSame(0, LeadWatchlistEntry::query()->withoutGlobalScope('tenant')->count());
    }
}
