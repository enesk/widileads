<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-034: Postleitzahl als eigene Spalte am Lead.
 *
 * Sie steht bereits als Antwort auf den reservierten Feldschluessel `plz` in
 * `lead_answers`. Fuer die Lead-Liste des Betreibers reicht das nicht: Ein
 * Filter auf PLZ-Praefix muesste sonst ueber einen Join auf einen JSON-Wert
 * gehen und koennte keinen Index nutzen -- bei 50.000 Leads waere das der
 * langsamste Teil der Seite.
 *
 * Dieselbe Ueberlegung wie bei `phone_e164` und `email_normalized` in FB-031:
 * Was gefiltert wird, steht als Spalte da. Die Rohantwort bleibt unangetastet
 * und ist weiterhin der Beweis (Architekturleitsatz 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('postal_code', 16)->nullable()->after('email_normalized');

            // Der Filter arbeitet mit Praefixen ("76%") -- dafuer greift ein
            // gewoehnlicher Index von links.
            $table->index(['tenant_id', 'postal_code']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'postal_code']);
            $table->dropColumn('postal_code');
        });
    }
};
