<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\FunnelFieldKey;
use App\Constants\FunnelStatus;
use App\Models\Funnel;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use Database\Seeders\FunnelExampleSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
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
        $question = FunnelQuestion::factory()->create(['field_key' => 'E-Mail']);

        $this->assertSame('e_mail', $question->field_key);
        $this->assertDatabaseHas('funnel_questions', ['id' => $question->id, 'field_key' => 'e_mail']);

        $question->update(['field_key' => '  Alter In Jahren ']);

        $this->assertSame('alter_in_jahren', $question->refresh()->field_key);
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

    public function test_reserved_field_keys_are_recognized(): void
    {
        $this->assertSame(
            ['vorname', 'nachname', 'name', 'email', 'telefon', 'plz', 'einwilligung'],
            FunnelFieldKey::values(),
        );

        $this->assertTrue(FunnelFieldKey::isReserved('Email'));
        $this->assertTrue(FunnelFieldKey::isReserved('Vorname'));
        $this->assertFalse(FunnelFieldKey::isReserved('tierart'));

        // "E-Mail" normalisiert zu "e_mail" und ist damit bewusst NICHT der
        // reservierte Schluessel "email" -- beide Vorgaben stehen so im Ticket.
        $this->assertSame('e_mail', FunnelFieldKey::normalize('E-Mail'));
        $this->assertFalse(FunnelFieldKey::isReserved('E-Mail'));

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

        // Zweiter Lauf legt nichts doppelt an.
        $this->seed(FunnelExampleSeeder::class);

        $this->assertSame(1, Funnel::query()->where('slug', 'beispiel-funnel')->count());
    }
}
