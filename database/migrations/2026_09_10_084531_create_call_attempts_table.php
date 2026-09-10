<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-082: Anrufversuche eines Kaeufers bei einem gekauften Lead.
 *
 * Jeder Versuch ist eine Zeile, samt der Rohmeldung von Twilio. Die Rohmeldung
 * ist der Beleg: Aus ihr laesst sich spaeter nachvollziehen, warum ein Versuch
 * so bewertet wurde, wie er bewertet wurde -- auch wenn sich die Regeln
 * (FB-083) inzwischen geaendert haben.
 *
 * Der Versuch haengt am Kaufbeleg, nicht nur am Lead: Bei einem geteilten Lead
 * (FB-055) ruft jeder Kaeufer fuer sich an.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_attempts', function (Blueprint $table) {
            $table->id();

            // Oeffentliche Kennung: Sie steht in den Rueckruf-Adressen, die
            // Twilio aufruft. Eine fortlaufende Nummer waere dort eine
            // Einladung, benachbarte Versuche durchzuprobieren.
            $table->ulid('uuid')->unique();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_purchase_id')->constrained('lead_purchases')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Was beim Lead als Rufnummernanzeige erscheint (bestaetigt ueber
            // FB-080) und welche Nummer gewaehlt wurde -- beide in E.164.
            $table->string('caller_number', 20);
            $table->string('lead_number', 20);

            // Erlaubte Werte: App\Constants\CallAttemptStatus.
            $table->string('status', 20)->default('queued');

            // Kennungen bei Twilio: der Anruf beim Mitarbeiter und der
            // durchgestellte Anruf beim Lead.
            $table->string('provider_call_sid', 64)->nullable()->unique();
            $table->string('provider_dial_sid', 64)->nullable();

            // Gespraechsdauer des Anrufs beim Lead in Sekunden. Sie allein
            // entscheidet spaeter ueber die Abrechnung (FB-083).
            $table->unsignedInteger('duration_seconds')->nullable();

            // Ergebnis der Anrufbeantworter-Erkennung von Twilio. Wird von
            // Anfang an mitgeschrieben, auch wenn erst FB-083 entscheidet, ob
            // eine Mailbox als erreicht zaehlt (Ticket #12).
            $table->string('answered_by', 32)->nullable();

            // Die letzte Rohmeldung von Twilio, unveraendert.
            $table->json('provider_payload')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            // Die Versuche zu einem Kauf, neueste zuerst -- so liest sie die
            // Detailseite und spaeter die Bewertung.
            $table->index(['lead_purchase_id', 'created_at']);

            // Die Versuche eines Workspaces.
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_attempts');
    }
};
