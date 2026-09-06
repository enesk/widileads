<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-037: Zeitpunkt der Anonymisierung eines Leads.
 *
 * Gesetzt bedeutet: der Personenbezug dieses Leads wurde entfernt. Zaehl- und
 * Preisdaten bleiben erhalten, damit Auswertungen und Abrechnungen der
 * Vergangenheit stimmig bleiben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('anonymized_at')->nullable()->after('settled_at');

            // Der taegliche Aufbewahrungslauf sucht genau danach: noch nicht
            // anonymisierte Leads, die alt genug sind.
            $table->index(['anonymized_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['anonymized_at', 'created_at']);
            $table->dropColumn('anonymized_at');
        });
    }
};
