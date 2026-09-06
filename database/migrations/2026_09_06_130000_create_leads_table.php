<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-030: Grundgeruest der Lead-Tabelle.
 *
 * Bewusst schlank: nur der Zustand und die Abrechnungsgrundlage. Funnel-Bezug,
 * Antworten, Score, Ergebnis und Kontaktdaten kommen additiv in FB-031 dazu,
 * sobald FB-010 (Funnel-Schema) steht -- deshalb gibt es hier noch keine
 * Fremdschluessel auf Funnel-Tabellen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Die EINZIGE Zustandsspalte eines Leads (Architekturleitsatz 1).
            // Als String und nicht als DB-Enum: neue Zustaende sollen ohne
            // Tabellenaenderung moeglich bleiben, gueltige Werte stehen in
            // App\Constants\LeadState.
            $table->string('lead_state', 20)->default('neu');

            // Beim Eintritt in einen Endzustand einmalig festgeschrieben und
            // danach nie geaendert (Architekturleitsatz 4).
            $table->decimal('settled_price', 8, 2)->nullable();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            // Traegt die Lead-Liste des Operators (FB-034) und den Marktplatz
            // (FB-053): beide filtern nach Mandant und Zustand und sortieren
            // nach Eingang.
            $table->index(['tenant_id', 'lead_state', 'created_at']);
            $table->index(['lead_state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
