<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-082: Die Bewertung eines Anrufversuchs.
 *
 * Die Tabelle selbst steht schon (create_call_attempts_table). Sie haelt bisher
 * nur fest, was Twilio gemeldet hat. Hier kommt dazu, was daraus folgt --
 * getrennt gehalten, weil das eine Beleg ist und das andere Auslegung: Aendern
 * sich die Regeln (FB-083), wird `outcome` neu berechnet, `provider_payload`
 * aber nie angefasst.
 *
 * Die Spalten des Tickets, die es hier schon gibt, bekommen keine Zwillinge:
 * `call_sid` ist `provider_call_sid` (eindeutig), `dial_call_sid` ist
 * `provider_dial_sid`, `duration_sec` ist `duration_seconds`, `raw_payload`
 * ist `provider_payload`, und der Kaeufer steht als `user_id` samt
 * `tenant_id` dran -- genauer als eine einzelne `buyer_id`, weil der Workspace
 * sehen muss, wer in seinem Namen angerufen hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_attempts', function (Blueprint $table) {
            // Wann der Lead abgenommen hat. Zusammen mit `ended_at` die
            // Gegenprobe zu `duration_seconds`.
            $table->timestamp('answered_at')->nullable()->after('started_at');

            // Stand des Lead-Legs, wie Twilio ihn im Dial-Rueckruf meldet
            // (completed, no-answer, busy, failed, canceled). Unveraendert
            // gespeichert -- `status` beschreibt den Anruf beim Mitarbeiter.
            $table->string('dial_status', 20)->nullable()->after('provider_dial_sid');

            // Erlaubte Werte: App\Constants\CallAttemptOutcome (answered,
            // failed_valid, failed_ignored). Null = noch nicht bewertet.
            $table->string('outcome', 20)->nullable()->after('answered_by');

            // Warum ein Versuch nicht zaehlt (z. B. Mindestabstand
            // unterschritten, Anruf vom Kaeufer selbst abgebrochen). Nur
            // gesetzt, wenn `outcome` failed_ignored ist.
            $table->string('ignore_reason', 40)->nullable()->after('outcome');

            // Die Zaehlung des Regelwerks: gueltige Versuche eines Leads in
            // zeitlicher Reihenfolge.
            $table->index(['lead_id', 'outcome', 'started_at']);
        });
    }

    /**
     * Der Umweg ueber den Fremdschluessel ist noetig: MySQL nutzt den
     * zusammengesetzten Index als Abdeckung des Fremdschluessels auf `leads`
     * und verweigert das Loeschen, solange der Fremdschluessel steht. Also
     * kurz abnehmen und danach wieder anlegen -- mit eigenem Index, wie er
     * ohne diese Migration entstanden waere.
     */
    public function down(): void
    {
        Schema::table('call_attempts', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropIndex(['lead_id', 'outcome', 'started_at']);

            $table->dropColumn([
                'answered_at',
                'dial_status',
                'outcome',
                'ignore_reason',
            ]);

            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });
    }
};
