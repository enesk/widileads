<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * FB-030d: Oeffentliche Kennung eines Leads.
 *
 * Die fortlaufende ID verlaesst die API nicht. Zwei Gruende: Sie verraet, wie
 * viele Leads die Plattform insgesamt hat, und Leads sind zwischen Workspaces
 * handelbar -- ihre Kennung darf also nicht nach einem Besitzer aussehen.
 *
 * Bestehende Zeilen bekommen ihre Kennung nachtraeglich, damit die Spalte
 * anschliessend eindeutig sein kann.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('leads')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($leads): void {
            foreach ($leads as $lead) {
                DB::table('leads')->where('id', $lead->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
