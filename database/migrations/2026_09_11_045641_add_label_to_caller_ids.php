<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eine Bezeichnung zur bestaetigten Rufnummer ("Buero", "Handy Enes").
 *
 * Reine Merkhilfe des Mitarbeiters, ohne Einfluss auf die Bestaetigung: Was
 * `status` ergibt, entscheidet weiterhin allein der CallerIdService anhand der
 * Rueckmeldung von Twilio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caller_ids', function (Blueprint $table): void {
            $table->string('label', 60)->nullable()->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('caller_ids', function (Blueprint $table): void {
            $table->dropColumn('label');
        });
    }
};
