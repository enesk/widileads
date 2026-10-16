<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Bekommen neu erzeugte Benutzer eine bestaetigte Rufnummer?
     *
     * Hintergrund ist die Telefonsperre EnsurePhoneVerified (LP-ONBOARD-003):
     * Sie steht vor der gesamten Portalgruppe, vor den Geldwegen des Kaeufers
     * und in der authMiddleware des Dashboard-Panels. Ein Benutzer ohne
     * bestaetigte Rufnummer bekommt dort 302 auf `onboarding.phone` statt der
     * Seite -- fuer einen Test, der etwas anderes prueft, ist das nur Rauschen.
     *
     * Die Vorgabe ist deshalb in Tests an (gesetzt in Tests\TestCase) und
     * ausserhalb aus: Seeder und Entwicklungsdaten sollen keine Bestaetigung
     * erfinden, die niemand durchlaufen hat. Wer die Sperre selbst prueft,
     * nimmt `phoneUnverified()` und sagt damit ausdruecklich, was er testet.
     */
    public static bool $phoneVerifiedByDefault = false;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
            'phone_number_verified_at' => static::$phoneVerifiedByDefault ? now() : null,
        ];
    }

    /**
     * Die Rufnummer ist bestaetigt -- der Nutzer kommt an der Telefonsperre
     * vorbei.
     *
     * Die Nummer selbst bleibt standardmaessig leer: Die Sperre liest nur den
     * Zeitstempel, und eine erfundene Nummer in jedem Testbenutzer kollidiert
     * mit der Pruefung "gehoert diese Nummer schon einem anderen Konto?"
     * (CallerIdService::guardAgainstForeignClaim).
     */
    public function phoneVerified(?string $phoneE164 = null): static
    {
        return $this->state(fn (array $attributes) => array_filter([
            'phone_number_verified_at' => now(),
            'phone_e164' => $phoneE164,
            'phone_number' => $phoneE164,
        ], static fn ($value) => $value !== null));
    }

    /**
     * Die Rufnummer ist nicht bestaetigt -- die Telefonsperre greift.
     *
     * Ausdruecklich und nicht als Vorgabe, damit ein Test, der die Sperre
     * prueft, das auch im Aufruf sagt.
     */
    public function phoneUnverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_number_verified_at' => null,
            'phone_verification_channel' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
