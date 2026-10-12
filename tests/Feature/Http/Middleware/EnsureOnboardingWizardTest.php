<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Constants\TenantType;
use App\Http\Middleware\EnsureOnboardingWizard;
use App\Models\BuyerRegistration;
use App\Models\CallerId;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Support\Facades\Route;
use Tests\Feature\FeatureTest;

/**
 * Ein Kaeufer mit offenem Einrichtungs-Wizard kommt im Portal nur auf den
 * naechsten Schritt (LP-ONBOARD-007).
 *
 * Geprueft wird die Fuehrung selbst, ihre Ausnahmeliste und die Grenze: Ein
 * Betreiber-Workspace hat keinen Wizard und darf nicht gefuehrt werden.
 *
 * Die Workspaces hier sagen ausdruecklich `onboardingPending()`. Die Vorgabe
 * der TenantFactory ist in Tests der Abschluss, damit Bestandstests, die etwas
 * anderes pruefen, nicht in dieser Umleitung landen.
 */
class EnsureOnboardingWizardTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withExceptionHandling();
    }

    public function test_buyer_with_an_open_wizard_is_sent_to_the_first_step(): void
    {
        [$tenant, $user] = $this->buyer();

        $this->actingAs($user)
            ->get(route('portal.marketplace', ['tenant' => $tenant->uuid]))
            ->assertRedirect(route('portal.onboarding', [
                'tenant' => $tenant->uuid,
                'step' => 'industries',
            ]));
    }

    public function test_the_wizard_itself_stays_reachable(): void
    {
        [$tenant, $user] = $this->buyer();

        $this->actingAs($user)
            ->get(route('portal.onboarding', ['tenant' => $tenant->uuid, 'step' => 'industries']))
            ->assertSuccessful();
    }

    /**
     * Die Rufnummernbestaetigung bleibt frei: Ein Kaeufer soll die Seite aus
     * dem Hinweis im Dashboard heraus ansteuern koennen, auch solange der
     * Wizard offen ist (Ticket #49).
     */
    public function test_the_caller_id_page_stays_reachable_without_a_verified_number(): void
    {
        $registration = BuyerRegistration::factory()->approved()->create([
            'tenant_id' => Tenant::factory()->buyer()->onboardingPending(),
        ]);
        $tenant = $registration->tenant->fresh();

        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $this->actingAs($user)
            ->get(route('portal.caller-id', ['tenant' => $tenant->uuid]))
            ->assertSuccessful();
    }

    public function test_a_completed_wizard_opens_the_portal(): void
    {
        [$tenant, $user] = $this->buyer(Tenant::factory()->buyer()->onboardingCompleted());

        $this->actingAs($user)
            ->get(route('portal.marketplace', ['tenant' => $tenant->uuid]))
            ->assertSuccessful();
    }

    public function test_a_skipped_wizard_opens_the_portal_too(): void
    {
        [$tenant, $user] = $this->buyer(Tenant::factory()->buyer()->onboardingSkipped());

        $this->actingAs($user)
            ->get(route('portal.marketplace', ['tenant' => $tenant->uuid]))
            ->assertSuccessful();
    }

    /**
     * Nur Kaeufer-Workspaces: Ein Betreiber hat weder Branchen noch Guthaben,
     * fuer ihn ist der Wizard gegenstandslos.
     */
    public function test_an_operator_workspace_is_not_guided(): void
    {
        // Eine eigene Route hinter genau dieser Middleware: Das Dashboard
        // eines Betreibers hat eigene Rechtepruefungen, die hier nur Rauschen
        // waeren -- gefragt ist, ob das Gate durchlaesst.
        Route::middleware(['web', 'auth', 'portal.tenant', 'portal.wizard'])
            ->get('/test/portal/{tenant:uuid}', fn (): string => 'durchgelassen')
            ->name('test.portal');

        $tenant = Tenant::factory()->onboardingPending()->create(['type' => TenantType::OPERATOR]);
        $user = User::factory()->create();
        $tenant->users()->attach($user);

        $this->actingAs($user)
            ->get('/test/portal/'.$tenant->uuid)
            ->assertSuccessful()
            ->assertSee('durchgelassen');
    }

    public function test_the_guidance_can_be_switched_off(): void
    {
        config()->set('funnel.onboarding.wizard_gate', false);

        [$tenant, $user] = $this->buyer();

        $this->actingAs($user)
            ->get(route('portal.marketplace', ['tenant' => $tenant->uuid]))
            ->assertSuccessful();
    }

    public function test_the_allow_list_covers_the_wizard_logout_and_the_caller_id_page(): void
    {
        $this->assertTrue(EnsureOnboardingWizard::allowsRoute('portal.onboarding'));
        $this->assertTrue(EnsureOnboardingWizard::allowsRoute('portal.onboarding.done'));
        $this->assertTrue(EnsureOnboardingWizard::allowsRoute('logout'));
        $this->assertTrue(EnsureOnboardingWizard::allowsRoute('portal.caller-id'));

        $this->assertFalse(EnsureOnboardingWizard::allowsRoute('portal.marketplace'));
        $this->assertFalse(EnsureOnboardingWizard::allowsRoute(null));
    }

    /**
     * Ein freigeschalteter Kaeufer mit bestaetigter Rufnummer -- damit nur das
     * Wizard-Gate uebrig bleibt und keine der beiden Sperren davor.
     *
     * @param  TenantFactory|null  $workspace  Der Zustand des Workspaces; ohne Angabe steht der Wizard offen
     * @return array{0: Tenant, 1: User}
     */
    private function buyer(?TenantFactory $workspace = null): array
    {
        $registration = BuyerRegistration::factory()->approved()->create([
            'tenant_id' => $workspace ?? Tenant::factory()->buyer()->onboardingPending(),
        ]);
        $tenant = $registration->tenant;

        $user = User::factory()->create();
        $tenant->users()->attach($user);

        CallerId::factory()->verified()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);

        return [$tenant->fresh(), $user];
    }
}
