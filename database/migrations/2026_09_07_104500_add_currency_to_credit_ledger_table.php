<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FB-052a: Waehrung einer Guthabenbuchung.
 *
 * `amount_cents` stand ohne Waehrungsangabe da, obwohl SaaSykit
 * mehrwaehrungsfaehig ist. Solange nur in einer Waehrung abgerechnet wird, faellt
 * das nicht auf -- und genau deshalb wird es nachgeholt, bevor echte Buchungen
 * existieren: Das Journal ist append-only, eine spaetere Korrektur waere kein
 * Update, sondern eine Wanderung ueber Belege, die man nicht mehr anfassen darf.
 *
 * In drei Schritten, damit die Spalte am Ende ohne Ausnahme gefuellt ist:
 * anlegen (zunaechst nullable), bestehende Zeilen mit der Standardwaehrung der
 * Installation fuellen, dann auf NOT NULL ziehen. Danach muss jede schreibende
 * Stelle die Waehrung nennen -- die Datenbank laesst ihr keine Wahl.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_ledger', function (Blueprint $table) {
            // ISO 4217, drei Zeichen. Sie gilt fuer die ganze Buchung, nicht nur
            // fuer amount_cents: Ein Guthabenkonto ist in einer Waehrung
            // gefuehrt, auch wenn eine Abbuchung selbst kein Geld bewegt.
            $table->char('currency', 3)->nullable()->after('amount_cents');
        });

        // Bestehende Buchungen stammen aus der Zeit vor dieser Spalte; sie
        // wurden in der Standardwaehrung der Installation gebucht.
        DB::table('credit_ledger')
            ->whereNull('currency')
            ->update(['currency' => self::defaultCurrency()]);

        Schema::table('credit_ledger', function (Blueprint $table) {
            $table->char('currency', 3)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('credit_ledger', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }

    private static function defaultCurrency(): string
    {
        return strtoupper((string) config('app.default_currency', 'EUR'));
    }
};
