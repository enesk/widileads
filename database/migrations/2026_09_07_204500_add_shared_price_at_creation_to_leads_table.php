<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FB-055a: Der Anteilspreis wird beim Anlegen eines Leads festgeschrieben.
 *
 * `price_at_creation` haelt seit FB-031 den Exklusivpreis fest. Fuer den
 * Mehrfachverkauf (FB-055) fehlte das Gegenstueck, weshalb dort der jeweils
 * aktuelle Funnelpreis galt: Aenderte ein Betreiber den Anteilspreis, aenderte
 * sich rueckwirkend, was ein bereits entstandener Lead kostet. Das
 * widerspricht Architekturleitsatz 4 -- Preise werden festgeschrieben.
 *
 * Bestandsleads bekommen den heute am Funnel hinterlegten Anteilspreis; wo
 * keiner hinterlegt ist, den Vorgabewert aus der Konfiguration. Das ist die
 * bestmoegliche Rekonstruktion: Ein anderer Wert hat fuer diese Leads nie
 * gegolten, weil es die Spalte noch nicht gab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('shared_price_at_creation', 8, 2)->nullable()->after('price_at_creation');
        });

        $default = (float) config('funnel.marketplace.sale.default_shared_price');

        DB::table('leads')
            ->whereNull('shared_price_at_creation')
            ->whereNotNull('funnel_id')
            ->update([
                'shared_price_at_creation' => DB::raw(sprintf(
                    'coalesce((select `shared_price` from `funnels` where `funnels`.`id` = `leads`.`funnel_id`), %s)',
                    $default,
                )),
            ]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('shared_price_at_creation');
        });
    }
};
