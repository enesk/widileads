<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Feature\FeatureTest;

/**
 * Die harte Sperre bis zur bestaetigten Rufnummer (LP-ONBOARD-003).
 *
 * Geprueft wird die Sperre selbst: der Umweg auf den Verifizierungs-Screen,
 * die kurze Ausnahmeliste, die maschinenlesbare Antwort fuer Livewire, die
 * Rueckkehr zur angefragten Adresse und die Wirkung eines Zuruecksetzens im
 * Admin.
 *
 * Die Testbenutzer sind seit diesem Ticket standardmaessig bestaetigt (siehe
 * UserFactory::$phoneVerifiedByDefault), damit Bestandstests nicht in die
 * Sperre laufen. Jeder Test hier sagt deshalb mit `phoneUnverified()`
 * ausdruecklich, dass er den gesperrten Fall meint.
 */
class EnsurePhoneVerifiedTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withExceptionHandling();
    }

    public function test_a_user_without_a_confirmed_number_is_sent_to_the_phone_page(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->phoneUnverified()->create();
        $tenant->users()->attach($user);

        $this->actingAs($user)
            ->get(route('portal.overview', ['tenant' => $tenant->uuid]))
            ->assertRedirect(route('onboarding.phone'));
    }

    public function test_the_phone_page_itself_stays_reachable(): void
    {
        $user = User::factory()->phoneUnverified()->create();

        $this->actingAs($user)
            ->get(route('onboarding.phone'))
            ->assertSuccessful();
    }

    /**
     * Die Ausnahmeliste ist ein Loch in der Sperre und deshalb kurz: Geprueft
     * wird, dass sie genau die Unterschritte des Verifizierungs-Screens und
     * die Pflichtangaben durchlaesst -- und sonst nichts.
     */
    public function test_only_the_whitelisted_route_names_pass_the_lock(): void
    {
        $this->defineGatedRoute('/test/sperre/code', 'onboarding.phone.code');
        $this->defineGatedRoute('/test/sperre/impressum', 'imprint.test-copy');
        $this->defineGatedRoute('/test/sperre/marktplatz', 'test.marketplace');

        $user = User::factory()->phoneUnverified()->create();
        $this->actingAs($user);

        $this->get('/test/sperre/code')->assertSuccessful();
        $this->get('/test/sperre/marktplatz')->assertRedirect(route('onboarding.phone'));

        // Die Pflichtangaben stehen unter dem Namen `imprint` auf der
        // Ausnahmeliste; die Kopie oben traegt absichtlich einen anderen Namen
        // und wird deshalb gesperrt -- die Liste ist eine Liste von Namen, kein
        // Pfadmuster.
        $this->get('/test/sperre/impressum')->assertRedirect(route('onboarding.phone'));
    }

    /**
     * Livewire wertet einen 302 auf eine Folgeanfrage nicht als Navigation --
     * der Nutzer bliebe auf der gesperrten Seite stehen. Deshalb 403 mit dem
     * Ziel im Rumpf.
     */
    public function test_a_livewire_request_gets_a_403_with_the_target(): void
    {
        $this->defineGatedRoute('/test/sperre/livewire', 'test.livewire');

        $user = User::factory()->phoneUnverified()->create();

        $this->actingAs($user)
            ->withHeader('X-Livewire', 'true')
            ->get('/test/sperre/livewire')
            ->assertForbidden()
            ->assertExactJson([
                'reason' => 'phone_unverified',
                'redirect' => route('onboarding.phone'),
            ]);
    }

    public function test_the_requested_address_is_reached_after_the_confirmation(): void
    {
        $this->defineGatedRoute('/test/sperre/ziel', 'test.target');

        $user = User::factory()->phoneUnverified()->create();

        $this->actingAs($user)
            ->get('/test/sperre/ziel')
            ->assertRedirect(route('onboarding.phone'));

        $this->assertSame(url('/test/sperre/ziel'), session('url.intended'));

        $user->forceFill(['phone_number_verified_at' => now()])->save();

        // Der Verifizierungs-Screen schliesst mit redirect()->intended() ab --
        // das ist die Rueckkehr an die Stelle, an der die Sperre griff.
        $this->actingAs($user->fresh())
            ->get(route('onboarding.phone'))
            ->assertRedirect(url('/test/sperre/ziel'));
    }

    /**
     * Die Geldwege des Kaeufers sind Formularziele (POST) und liegen hinter
     * derselben Sperre. Eine gemerkte POST-Adresse waere schaedlich: Sie wuerde
     * nach der Bestaetigung als GET aufgerufen und endete in 405.
     */
    public function test_a_money_path_is_locked_too_and_is_not_remembered(): void
    {
        $user = User::factory()->phoneUnverified()->create();

        $this->actingAs($user)
            ->post(route('buyer.payment-methods.setup-intent'))
            ->assertRedirect(route('onboarding.phone'));

        $this->assertNull(session('url.intended'));
    }

    /**
     * Zuruecksetzen im Admin wirkt in der naechsten Anfrage -- ohne Abmelden,
     * ohne Cache (LP-ONBOARD-017).
     */
    public function test_resetting_the_confirmation_locks_the_user_again(): void
    {
        $this->defineGatedRoute('/test/sperre/erneut', 'test.again');

        $user = User::factory()->phoneVerified()->create();

        $this->actingAs($user)
            ->get('/test/sperre/erneut')
            ->assertSuccessful();

        $user->forceFill(['phone_number_verified_at' => null])->save();

        $this->actingAs($user->fresh())
            ->get('/test/sperre/erneut')
            ->assertRedirect(route('onboarding.phone'));
    }

    public function test_the_lock_can_be_switched_off(): void
    {
        config()->set('funnel.onboarding.phone_gate', false);

        $this->defineGatedRoute('/test/sperre/aus', 'test.disabled');

        $user = User::factory()->phoneUnverified()->create();

        $this->actingAs($user)
            ->get('/test/sperre/aus')
            ->assertSuccessful();
    }

    /**
     * Eine Route, die nur die Sperre vor sich hat. So haengt das Ergebnis nicht
     * an den weiteren Gates des Portals (Workspace, Rufnummer des Anrufers,
     * Wizard), die hier nicht geprueft werden.
     */
    private function defineGatedRoute(string $uri, string $name): void
    {
        Route::middleware(['web', 'auth', 'portal.phone-verified'])
            ->get($uri, fn (): string => 'offen')
            ->name($name);
    }
}
