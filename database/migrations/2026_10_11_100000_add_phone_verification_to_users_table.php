<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-ONBOARD-004: Die bestaetigte Rufnummer eines Nutzers.
 *
 * `phone_e164` ist die waehlbare Form der Nummer und damit die, mit der
 * spaeter telefoniert wird. `phone_number` daneben bleibt stehen: Dort steht,
 * was der Nutzer eingetippt hat, und die mitgelieferte SMS-Verifikation von
 * SaaSykit liest es. Wer anrufen will, nimmt `phone_e164` -- eine Eingabe wie
 * "0172 1234567" kann Twilio nicht waehlen.
 *
 * Der Index auf `phone_e164` tut zwei Dinge, die beide Fachlogik sind: Er
 * traegt die Pruefung "gehoert diese Nummer schon einem anderen Konto?" und
 * die Rate-Limits je Nummer. Beide laufen bei jeder Code-Anforderung.
 *
 * **Kein zweiter Zeitstempel.** Das Ticket nennt `phone_verified_at`; die
 * Wahrheit liegt aber schon in `users.phone_number_verified_at` (seit
 * SaaSykit) und genau dort lesen die Sperre EnsurePhoneVerified, der
 * OnboardingService, UserService::anonymize und die Portal-Routen
 * (LP-ONBOARD-003). Eine zweite Spalte waere eine zweite Wahrheit: Jeder
 * kuenftige Leser muesste wissen, welche der beiden gilt -- und die Sperre
 * haette beim Fehlgriff ein Loch. Deshalb nur die beiden neuen Spalten.
 *
 * Rein additiv, down() nimmt genau sie zurueck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_e164', 32)->nullable()->after('phone_number');

            // Auf welchem Weg der Code kam: App\Constants\PhoneVerificationChannel.
            // Fuer den Support ("er sagt, es kam keine SMS") und fuer die
            // Frage, ob der Anruf-Ausweg schon genutzt wurde.
            $table->string('phone_verification_channel', 8)->nullable()->after('phone_number_verified_at');

            $table->index('phone_e164');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone_e164']);
            $table->dropColumn(['phone_e164', 'phone_verification_channel']);
        });
    }
};
