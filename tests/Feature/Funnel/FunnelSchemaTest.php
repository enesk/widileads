<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\ConditionOperator;
use App\Constants\FunnelFieldKey;
use App\Constants\FunnelStatus;
use App\Models\Funnel;
use App\Models\FunnelCondition;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use Database\Seeders\FunnelExampleSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FeatureTest;

/**
 * FB-010: Datenmodell Funnel / Steps / Questions / Options.
 */
class FunnelSchemaTest extends FeatureTest
{
    protected function tearDown(): void
    {
        Filament::setTenant(null, isQuiet: true);

        parent::tearDown();
    }

    public function test_field_key_is_unique_per_funnel_across_steps(): void
    {
        $funnel = Funnel::factory()->create();
        $firstStep = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 1]);
        $secondStep = FunnelStep::factory()->create(['funnel_id' => $funnel->id, 'position' => 2]);

        FunnelQuestion::factory()->create(['step_id' => $firstStep->id, 'field_key' => 'tierart']);

        $this->expectException(QueryException::class);

        FunnelQuestion::factory()->create(['step_id' => $secondStep->id, 'field_key' => 'tierart']);
    }

    public function test_the_same_field_key_may_be_used_in_another_funnel(): void
    {
        $firstFunnel = Funnel::factory()->create();
        $secondFunnel = Funnel::factory()->create();

        FunnelQuestion::factory()->create([
            'step_id' => FunnelStep::factory()->create(['funnel_id' => $firstFunnel->id])->id,
            'field_key' => 'tierart',
        ]);
        FunnelQuestion::factory()->create([
            'step_id' => FunnelStep::factory()->create(['funnel_id' => $secondFunnel->id])->id,
            'field_key' => 'tierart',
        ]);

        $this->assertSame(1, $firstFunnel->questions()->count());
        $this->assertSame(1, $secondFunnel->questions()->count());
    }

    public function test_field_key_is_normalized_on_save(): void
    {
        $question = FunnelQuestion::factory()->create(['field_key' => 'Alter In Jahren']);

        $this->assertSame('alter_in_jahren', $question->field_key);
        $this->assertDatabaseHas('funnel_questions', ['id' => $question->id, 'field_key' => 'alter_in_jahren']);

        $question->update(['field_key' => '  Groesse Des Tieres ']);

        $this->assertSame('groesse_des_tieres', $question->refresh()->field_key);
    }

    public function test_a_contact_field_labelled_e_mail_ends_up_as_the_reserved_key(): void
    {
        $question = FunnelQuestion::factory()->create(['field_key' => 'E-Mail']);

        // Erster Schritt bleibt die Normalisierung aus dem Ticket ...
        $this->assertSame('e_mail', FunnelFieldKey::normalize('E-Mail'));

        // ... zweiter Schritt loest den Alias auf, sonst faende LeadContact
        // (FB-032) spaeter keine E-Mail-Adresse an diesem Lead.
        $this->assertSame('email', $question->field_key);
        $this->assertDatabaseHas('funnel_questions', ['id' => $question->id, 'field_key' => 'email']);
        $this->assertTrue($question->hasReservedFieldKey());
    }

    /**
     * Schreibweisen, die zwingend aufgeloest werden muessen. Die Liste ist die
     * Vorgabe an config('funnel.field_key_aliases') -- dort duerfen weitere
     * Aliase stehen, diese hier aber nicht fehlen.
     *
     * @return array<string, array{string, string}>
     */
    public static function aliasProvider(): array
    {
        return [
            'e_mail' => ['E-Mail', 'email'],
            'mail' => ['Mail', 'email'],
            'email_adresse' => ['Email Adresse', 'email'],
            'telefonnummer' => ['Telefonnummer', 'telefon'],
            'tel' => ['Tel', 'telefon'],
            'mobil' => ['Mobil', 'telefon'],
            'handy' => ['Handy', 'telefon'],
            'postleitzahl' => ['Postleitzahl', 'plz'],
            'plz_ort' => ['PLZ Ort', 'plz'],
            'vor_name' => ['Vor Name', 'vorname'],
            'nach_name' => ['Nach Name', 'nachname'],
            'familienname' => ['Familienname', 'nachname'],
            'datenschutz' => ['Datenschutz', 'einwilligung'],
            'zustimmung' => ['Zustimmung', 'einwilligung'],
            'einwilligung_datenschutz' => ['Einwilligung Datenschutz', 'einwilligung'],
        ];
    }

    #[DataProvider('aliasProvider')]
    public function test_each_required_alias_resolves_to_its_reserved_key(string $label, string $reservedKey): void
    {
        $this->assertSame($reservedKey, FunnelFieldKey::resolve($label));
        $this->assertTrue(FunnelFieldKey::isReserved($label));

        $question = FunnelQuestion::factory()->create(['field_key' => $label]);

        $this->assertSame($reservedKey, $question->field_key);
    }

    public function test_every_configured_alias_points_at_a_reserved_key(): void
    {
        /** @var array<string, string> $aliases */
        $aliases = config('funnel.field_key_aliases');

        $this->assertNotSame([], $aliases);

        foreach ($aliases as $alias => $reservedKey) {
            $this->assertSame(
                $alias,
                FunnelFieldKey::normalize($alias),
                sprintf('Der Alias "%s" ist selbst nicht normalisiert und wuerde nie greifen.', $alias),
            );
            $this->assertNotNull(FunnelFieldKey::tryFrom($reservedKey), sprintf(
                'Der Alias "%s" zeigt auf "%s" - das ist kein reservierter Feldschluessel.',
                $alias,
                $reservedKey,
            ));
        }
    }

    public function test_unknown_field_keys_stay_untouched(): void
    {
        $this->assertSame('tierart', FunnelFieldKey::resolve('Tierart'));
        $this->assertFalse(FunnelFieldKey::isReserved('Tierart'));
    }

    public function test_an_alias_pointing_at_an_unknown_key_is_ignored(): void
    {
        config()->set('funnel.field_key_aliases', ['wunschtermin' => 'gibt_es_nicht']);

        $this->assertSame('wunschtermin', FunnelFieldKey::resolve('Wunschtermin'));
        $this->assertFalse(FunnelFieldKey::isReserved('Wunschtermin'));
    }

    public function test_question_inherits_the_funnel_from_its_step(): void
    {
        $funnel = Funnel::factory()->create();
        $step = FunnelStep::factory()->create(['funnel_id' => $funnel->id]);

        $question = FunnelQuestion::factory()->create(['step_id' => $step->id]);

        $this->assertSame($funnel->id, $question->funnel_id);
    }

    public function test_public_token_is_generated_and_used_as_route_key(): void
    {
        $funnel = Funnel::query()->create([
            'tenant_id' => $this->createTenant()->id,
            'name' => 'Pfotencheck Light',
        ]);

        $this->assertTrue(Str::isUlid($funnel->public_token));
        $this->assertSame('public_token', $funnel->getRouteKeyName());
        $this->assertSame($funnel->public_token, $funnel->getRouteKey());
        $this->assertSame('pfotencheck-light', $funnel->slug);
    }

    public function test_lead_price_falls_back_to_the_configured_default(): void
    {
        config()->set('funnel.lead.default_price', 15.00);

        $withoutPrice = Funnel::factory()->create(['lead_price' => null]);
        $withPrice = Funnel::factory()->create(['lead_price' => 24.50]);

        $this->assertSame(15.00, $withoutPrice->effectiveLeadPrice());
        $this->assertSame(24.50, $withPrice->effectiveLeadPrice());
    }

    public function test_status_is_cast_and_published_scope_filters(): void
    {
        $draft = Funnel::factory()->create();
        $published = Funnel::factory()->published()->create();
        Funnel::factory()->archived()->create();

        $this->assertSame(FunnelStatus::DRAFT, $draft->status);
        $this->assertFalse($draft->isPublished());
        $this->assertTrue($published->isPublished());
        $this->assertSame([$published->id], Funnel::query()->published()->pluck('id')->all());
    }

    public function test_funnels_are_scoped_to_the_active_tenant(): void
    {
        $ownTenant = $this->createTenant();
        $otherTenant = $this->createTenant();

        $ownFunnel = Funnel::factory()->forTenant($ownTenant)->create();
        Funnel::factory()->forTenant($otherTenant)->create();

        Filament::setTenant($ownTenant, isQuiet: true);

        $this->assertSame([$ownFunnel->id], Funnel::query()->pluck('id')->all());
        $this->assertSame(2, Funnel::query()->withoutGlobalScopes()->count());
    }

    public function test_tenant_id_is_set_from_the_active_tenant(): void
    {
        $tenant = $this->createTenant();
        Filament::setTenant($tenant, isQuiet: true);

        $funnel = Funnel::query()->create(['name' => 'Ohne Mandantenangabe']);

        $this->assertSame($tenant->id, $funnel->tenant_id);
    }

    public function test_deleting_a_funnel_removes_steps_questions_and_options(): void
    {
        $funnel = Funnel::factory()->create();
        $step = FunnelStep::factory()->create(['funnel_id' => $funnel->id]);
        $question = FunnelQuestion::factory()->create(['step_id' => $step->id]);
        $option = FunnelOption::factory()->create(['question_id' => $question->id]);

        $funnel->delete();

        $this->assertDatabaseMissing('funnel_steps', ['id' => $step->id]);
        $this->assertDatabaseMissing('funnel_questions', ['id' => $question->id]);
        $this->assertDatabaseMissing('funnel_options', ['id' => $option->id]);
    }

    public function test_conditions_link_question_and_target_step_within_one_funnel(): void
    {
        $condition = FunnelCondition::factory()->withPriority(10)->create();

        $this->assertSame($condition->funnel_id, $condition->sourceQuestion->funnel_id);
        $this->assertSame($condition->funnel_id, $condition->targetStep->funnel_id);
        $this->assertSame(ConditionOperator::EQUALS, $condition->operator);
        $this->assertSame(['ja'], $condition->value);
        $this->assertSame(10, $condition->priority);
        $this->assertSame([$condition->id], $condition->funnel->conditions()->pluck('id')->all());
    }

    public function test_every_condition_operator_is_persistable_and_labelled(): void
    {
        $this->assertSame(
            ['equals', 'not_equals', 'in', 'gt', 'lt', 'contains', 'answered', 'score_gte'],
            ConditionOperator::values(),
        );

        foreach (ConditionOperator::cases() as $operator) {
            $condition = FunnelCondition::factory()->withOperator($operator)->create();

            $this->assertSame($operator, $condition->refresh()->operator);
            $this->assertNotSame(
                'funnel.condition_operator.'.$operator->value,
                $operator->label(),
                sprintf('Fuer den Operator "%s" fehlt eine Beschriftung in lang/de/funnel.php.', $operator->value),
            );
        }
    }

    public function test_results_carry_their_score_range(): void
    {
        $funnel = Funnel::factory()->create();

        FunnelResult::factory()->forScoreRange(0, 3)->create(['funnel_id' => $funnel->id, 'title' => 'Geringes Risiko']);
        FunnelResult::factory()->forScoreRange(4, 7)->create(['funnel_id' => $funnel->id, 'title' => 'Erhoehtes Risiko']);

        $results = $funnel->results()->orderBy('min_score')->get();

        $this->assertCount(2, $results);
        $this->assertSame('Geringes Risiko', $results[0]->title);
        $this->assertSame(3, $results[0]->max_score);
        $this->assertTrue($results[0]->show_contact_form);
    }

    public function test_deleting_a_funnel_removes_conditions_and_results(): void
    {
        $condition = FunnelCondition::factory()->create();
        $funnel = $condition->funnel;
        $result = FunnelResult::factory()->create(['funnel_id' => $funnel->id]);

        $funnel->delete();

        $this->assertDatabaseMissing('funnel_conditions', ['id' => $condition->id]);
        $this->assertDatabaseMissing('funnel_results', ['id' => $result->id]);
    }

    public function test_reserved_field_keys_are_recognized(): void
    {
        $this->assertSame(
            ['vorname', 'nachname', 'name', 'email', 'telefon', 'plz', 'einwilligung'],
            FunnelFieldKey::values(),
        );

        $this->assertTrue(FunnelFieldKey::isReserved('Email'));
        $this->assertTrue(FunnelFieldKey::isReserved('Vorname'));
        $this->assertFalse(FunnelFieldKey::isReserved('tierart'));

        $question = FunnelQuestion::factory()->create(['field_key' => 'Telefon']);

        $this->assertTrue($question->hasReservedFieldKey());
    }

    public function test_options_keep_their_order_and_score(): void
    {
        $question = FunnelQuestion::factory()->create();
        FunnelOption::factory()->withScore(8)->create(['question_id' => $question->id, 'position' => 2, 'label' => 'Katze']);
        FunnelOption::factory()->withScore(10)->create(['question_id' => $question->id, 'position' => 1, 'label' => 'Hund']);

        $labels = $question->options()->pluck('label')->all();

        $this->assertSame(['Hund', 'Katze'], $labels);
        $this->assertSame(10, $question->options()->first()->score);
    }

    public function test_example_seeder_creates_a_complete_funnel(): void
    {
        $this->createTenant();

        $this->seed(FunnelExampleSeeder::class);

        $funnel = Funnel::query()->where('slug', 'beispiel-funnel')->sole();

        $this->assertSame(2, $funnel->steps()->count());
        $this->assertSame(8, $funnel->questions()->count());
        $this->assertTrue(Str::isUlid($funnel->public_token));
        $this->assertSame(3, $funnel->questions()->where('field_key', 'tierart')->sole()->options()->count());
        $this->assertSame(1, $funnel->conditions()->count());
        $this->assertSame(3, $funnel->results()->count());

        // Zweiter Lauf legt nichts doppelt an.
        $this->seed(FunnelExampleSeeder::class);

        $this->assertSame(1, Funnel::query()->where('slug', 'beispiel-funnel')->count());
    }
}
