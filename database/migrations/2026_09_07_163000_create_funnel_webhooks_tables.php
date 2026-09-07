<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-030e: Webhooks je Funnel und ihr Zustellprotokoll.
 *
 * Das Secret liegt verschluesselt in der Spalte: Es muss zum Signieren im
 * Klartext vorliegen, darf aber in einem Datenbank-Abzug nicht lesbar sein --
 * wer es hat, kann Ereignisse faelschen, die der Empfaenger fuer echt haelt.
 *
 * webhook_deliveries ist das Protokoll fuer die Fehlersuche: Wenn ein Empfaenger
 * Ereignisse vermisst, ist hier nachlesbar, ob wir zugestellt haben und was
 * zurueckkam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->string('url');
            $table->text('secret');
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();

            $table->index(['funnel_id', 'active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('funnel_webhooks')->cascadeOnDelete();
            // Ueber alle Versuche stabil: Der Empfaenger erkennt daran eine
            // Wiederholung und verarbeitet sie nicht zweimal.
            $table->uuid('event_id');
            $table->string('event', 40);
            $table->json('payload');
            $table->string('status', 20);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'status']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('funnel_webhooks');
    }
};
