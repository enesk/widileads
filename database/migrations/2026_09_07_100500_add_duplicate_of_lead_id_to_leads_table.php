<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-033: Verweis auf einen moeglichen Vorgaenger desselben Kontakts.
 *
 * Gefunden hat ihn der DuplicateLeadFinder aus FB-023, entschieden wird erst
 * hier: Der Verweis steht am Lead, damit der Pruefjob ihn nach einem
 * Warteschlangen-Neustart noch vorfindet und damit spaeter nachvollziehbar
 * bleibt, warum ein Lead als Dublette verworfen wurde.
 *
 * `nullOnDelete`: Verschwindet der Vorgaenger, bleibt dieser Lead bestehen --
 * er traegt moeglicherweise eine Forderung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('duplicate_of_lead_id')
                ->nullable()
                ->after('public_session_id')
                ->constrained('leads')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['duplicate_of_lead_id']);
            $table->dropColumn('duplicate_of_lead_id');
        });
    }
};
