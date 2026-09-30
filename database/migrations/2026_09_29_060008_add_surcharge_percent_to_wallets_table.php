<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abweichender Pay-as-you-go-Aufschlag je Kaeufer.
 *
 * `surcharge_percent` ist ein Override und liegt damit genau dort, wo auch
 * `payment_mode` und `credit_limit_cents` stehen: am Kauf-Wallet. null heisst,
 * es gilt config('wallet.postpaid.surcharge_percent'); 0 heisst, dieser
 * Kaeufer zahlt gar keinen Aufschlag. Dasselbe Muster wie
 * `tenants.commission_percent` seit LP-WALLET-003.
 *
 * Die Spalte wirkt nur beim Reservieren: Der Satz wird dort als
 * `lead_purchases.surcharge_cents` festgeschrieben und danach nie wieder
 * nachgeschlagen (Architekturleitsatz 4). Eine Aenderung gilt fuer kuenftige
 * Kaeufe, nicht rueckwirkend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('surcharge_percent', 5, 2)->nullable()->after('credit_limit_cents');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('surcharge_percent');
        });
    }
};
