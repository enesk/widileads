<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-030c: Freitext zur Veroeffentlichung.
 *
 * Die Versionshistorie beantwortet sonst nur, WANN veroeffentlicht wurde, nicht
 * WARUM. Bei einem Funnel, der ueber Monate laeuft, ist genau das die Frage,
 * wenn eine Fassung zurueckverfolgt werden muss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_versions', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_versions', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
