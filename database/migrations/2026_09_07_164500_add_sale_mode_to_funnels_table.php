<?php

declare(strict_types=1);

use App\Constants\SaleMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-055: Verkaufsart je Funnel.
 *
 * Bestehende Funnels verkaufen exklusiv -- das ist die Vorgabe (Entscheidung 2
 * vom 2026-09-06) und zugleich das bisherige Verhalten. Der Vorgabewert auf
 * Spaltenebene ist hier richtig: Ein Funnel ohne Angabe soll sich weiter genau
 * so verhalten wie vor dieser Migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            // Erlaubte Werte: App\Constants\SaleMode.
            $table->string('sale_mode', 20)->default(SaleMode::EXCLUSIVE->value)->after('lead_price');

            // Hoechstzahl an Kaeufern je Lead. Bei `exclusive` immer 1.
            $table->unsignedInteger('max_buyers')->default(1)->after('sale_mode');

            // Preis je Kaeufer bei `shared`. Ohne eigenen Wert gilt der
            // Vorgabewert aus config('funnel.marketplace.sale.default_shared_price').
            $table->decimal('shared_price', 8, 2)->nullable()->after('max_buyers');
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropColumn(['sale_mode', 'max_buyers', 'shared_price']);
        });
    }
};
