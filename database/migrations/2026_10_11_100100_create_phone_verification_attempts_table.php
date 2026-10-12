<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-ONBOARD-004: Das Protokoll der Bestaetigungsversuche.
 *
 * Hier liegt bewusst kein Code. Twilio Verify haelt die Codes, ihre Laufzeit
 * und ihre Pruefung; wir speichern nur, dass ein Versuch stattgefunden hat.
 * Ein selbst gespeicherter Code waere ein Geheimnis in unserer Datenbank, das
 * wir nicht brauchen -- und ein zweiter Weg, eine Nummer zu bestaetigen.
 *
 * Zwei Leser, und beide bestimmen das Schema:
 *
 * 1. Die Rate-Limits. "3 Anforderungen je Nummer je Stunde" ist genau eine
 *    Abfrage auf (phone_e164, created_at) -- deshalb der zusammengesetzte
 *    Index in dieser Reihenfolge.
 * 2. Der Support. "Wann ging bei dieser Nummer welche SMS raus und was sagte
 *    Twilio?" laeuft ueber `verification_sid`, mit der sich der Vorgang in der
 *    Twilio-Konsole wiederfinden laesst.
 *
 * Nur `created_at`, kein `updated_at`: Eine Zeile ist das Protokoll eines
 * Moments. Der Stand aendert sich allerdings noch einmal, wenn die Pruefung
 * den Versuch auf `approved` setzt -- das ist dasselbe Ereignis und keine neue
 * Zeile.
 *
 * `user_id` ist nullable: Loescht ein Nutzer sein Konto, bleibt das Protokoll
 * fuer die Limits bestehen. Sonst waere das Loeschen des Kontos der Weg, eine
 * gesperrte Nummer wieder freizuspielen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_verification_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Immer E.164 -- die Limits zaehlen auf Gleichheit, und
            // "0172 1234567" und "+49172..." waeren sonst zwei Nummern.
            $table->string('phone_e164', 32);

            // App\Constants\PhoneVerificationChannel.
            $table->string('channel', 8);

            // Die Kennung des Vorgangs bei Twilio ("VE..."). Null, wenn Twilio
            // die Anforderung abgelehnt hat.
            $table->string('verification_sid', 64)->nullable();

            // App\Constants\PhoneVerificationStatus.
            $table->string('status', 16);

            $table->timestamp('created_at')->nullable();

            // Die Limitabfrage: Versuche einer Nummer im Zeitfenster.
            $table->index(['phone_e164', 'created_at']);

            // Der Blick des Supports auf ein Konto.
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verification_attempts');
    }
};
