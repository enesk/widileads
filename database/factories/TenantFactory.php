<?php

namespace Database\Factories;

use App\Constants\TenantType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Gilt der Einrichtungs-Wizard in neu erzeugten Workspaces als
     * abgeschlossen?
     *
     * Hintergrund ist das Wizard-Gate EnsureOnboardingWizard (LP-ONBOARD-007):
     * Es fuehrt jeden Kaeufer-Workspace mit offenem Wizard auf
     * `portal.onboarding` -- also jeden Workspace aus dieser Factory, denn sie
     * setzt die beiden Zeitstempel nicht. Ein Test, der eine Portalseite oder
     * das Dashboard-Panel aufruft, bekaeme damit 302 statt der Seite, obwohl er
     * etwas voellig anderes prueft.
     *
     * Die Vorgabe ist deshalb in Tests an (gesetzt in Tests\TestCase) und
     * ausserhalb aus: Seeder und Entwicklungsdaten sollen keinen Abschluss
     * erfinden, den niemand durchlaufen hat. Wer die Fuehrung selbst prueft,
     * nimmt `onboardingPending()` und sagt damit ausdruecklich, was er testet.
     *
     * Dasselbe Muster wie UserFactory::$phoneVerifiedByDefault fuer die
     * Telefonsperre daneben.
     */
    public static bool $onboardingCompletedByDefault = false;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'uuid' => fake()->uuid(),
            'type' => TenantType::OPERATOR,
            'onboarding_completed_at' => static::$onboardingCompletedByDefault ? now() : null,
        ];
    }

    /**
     * Der Einrichtungs-Wizard ist abgeschlossen -- der Workspace kommt am
     * Wizard-Gate vorbei.
     *
     * Die einzelnen Haken in `tenant_onboarding_steps` entstehen dabei nicht:
     * Das Gate liest nur den Zeitstempel. Wer die Checkliste prueft, hakt die
     * Schritte ueber den OnboardingService ab.
     */
    public function onboardingCompleted(): static
    {
        return $this->state(fn (): array => [
            'onboarding_completed_at' => now(),
            'onboarding_skipped_at' => null,
        ]);
    }

    /**
     * Der Wizard ist uebersprungen -- fuer das Gate dasselbe Ergebnis wie
     * abgeschlossen, fuer die Aktivierungskarte nicht.
     */
    public function onboardingSkipped(): static
    {
        return $this->state(fn (): array => [
            'onboarding_completed_at' => null,
            'onboarding_skipped_at' => now(),
        ]);
    }

    /**
     * Der Wizard steht noch offen -- das Gate fuehrt diesen Workspace.
     *
     * Ausdruecklich und nicht als Vorgabe, damit ein Test, der die Fuehrung
     * prueft, das auch im Aufruf sagt.
     */
    public function onboardingPending(): static
    {
        return $this->state(fn (): array => [
            'onboarding_completed_at' => null,
            'onboarding_skipped_at' => null,
        ]);
    }

    /**
     * Betreiber-Tenant: baut und veroeffentlicht Funnels.
     */
    public function operator(): static
    {
        return $this->state(fn (): array => ['type' => TenantType::OPERATOR]);
    }

    /**
     * Kaeufer-Tenant: kauft Leads ueber den Marktplatz.
     */
    public function buyer(): static
    {
        return $this->state(fn (): array => ['type' => TenantType::BUYER]);
    }
}
