<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-080: Bestaetigte Rufnummer eines Kaeufer-Mitarbeiters.
 *
 * Eigene Tabelle statt weiterer Felder an `users`: `users.phone_number` gehoert
 * der SMS-Bestaetigung aus SaaSykit (Trial ohne Zahlungsdaten). Beide Zwecke in
 * dieselben Spalten zu legen hiesse, dass eine bestandene SMS-Pruefung eine
 * Rufnummernanzeige freischaltet, die Twilio nie angerufen hat.
 *
 * Eine Nummer je Benutzer -- das ist die Entscheidung aus Ticket #12, nicht
 * eine technische Grenze. Der Mandant steht daneben, weil der Kaeufer-Workspace
 * sieht, wer in seinem Namen anruft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caller_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Immer E.164, normalisiert beim Speichern.
            $table->string('phone_number', 20);

            // Erlaubte Werte: App\Constants\CallerIdStatus.
            $table->string('status', 20)->default('pending');

            // Kennung der Validation Request bei Twilio. Der Rueckruf traegt sie
            // nicht mit, die Zuordnung laeuft ueber die Nummer -- die Kennung
            // dient dem Nachvollziehen im Fehlerfall.
            $table->string('validation_sid', 64)->nullable();

            // Der Code, den Twilio am Telefon ansagt. Kein Geheimnis, sondern
            // eine Anzeige: Der Mitarbeiter tippt ihn nicht ein, er hoert ihn.
            $table->string('validation_code', 16)->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            // Eine Nummer je Benutzer.
            $table->unique('user_id');

            // Der Rueckruf von Twilio kennt nur die Nummer.
            $table->index('phone_number');

            // Die Uebersicht des Workspace: wer hat bestaetigt, wer nicht.
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caller_ids');
    }
};
