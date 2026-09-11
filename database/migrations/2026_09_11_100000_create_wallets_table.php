<?php

declare(strict_types=1);

use App\Constants\WalletOwnerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LP-WALLET-003: Die Geldtoepfe des Marktplatzes.
 *
 * Ein Wallet gehoert einer Rolle, nicht einem Mandanten: Derselbe Mandant kann
 * als Kaeufer und als Verkaeufer auftreten und braucht dann zwei getrennte
 * Toepfe mit getrennten Ledgern (App\Constants\WalletOwnerType). Das Wallet der
 * Plattform hat keinen Besitzer und traegt deshalb `owner_id = null`.
 *
 * `balance_cents` und `reserved_cents` sind fortgeschriebene Salden, die
 * Wahrheit bleibt das Journal in `wallet_transactions`. Beide Spalten sind
 * bigint: Plattform-Summen laufen ueber Jahre auf, ein int liefe darin zu
 * frueh ueber.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // Erlaubte Werte: App\Constants\WalletOwnerType. Als String und
            // nicht als DB-Enum, wie ueberall im Funnel Builder (leads.lead_state,
            // funnels.sale_mode): neue Rollen sollen ohne Tabellenaenderung
            // moeglich bleiben.
            $table->string('owner_type', 20);

            // Null ausschliesslich beim Plattform-Wallet. Ein Unique-Index
            // schliesst in MySQL mehrere NULL-Werte nicht aus; dass es genau ein
            // Plattform-Wallet gibt, sichert deshalb der PlatformWalletSeeder
            // bzw. der WalletService ueber firstOrCreate.
            $table->foreignId('owner_id')->nullable()->constrained('tenants')->cascadeOnDelete();

            // Waehrung aller Buchungen dieses Wallets. Ein Saldo aus gemischten
            // Waehrungen waere nicht aufsummierbar, deshalb haengt sie am
            // Wallet und nicht an der einzelnen Buchung.
            $table->char('currency', 3)->default('EUR');

            // Frei verfuegbares Guthaben.
            $table->bigInteger('balance_cents')->default(0);

            // Fuer laufende Leadkaeufe geblockter Teil. Bewusst getrennt vom
            // freien Saldo: reserviertes Geld darf kein zweites Mal ausgegeben
            // werden, ist aber auch noch nicht abgebucht.
            $table->bigInteger('reserved_cents')->default(0);

            $table->timestamps();

            // Je Rolle und Besitzer genau ein Wallet.
            $table->unique(['owner_type', 'owner_id']);
        });

        // Das Plattform-Wallet gehoert zur Grundausstattung: ohne es kann keine
        // Provision gebucht werden. Es entsteht deshalb schon hier und nicht
        // erst beim Seeden -- der PlatformWalletSeeder legt denselben Datensatz
        // idempotent noch einmal an, falls er von Hand geloescht wurde.
        DB::table('wallets')->insert([
            'owner_type' => WalletOwnerType::PLATFORM->value,
            'owner_id' => null,
            'currency' => config('wallet.currency'),
            'balance_cents' => 0,
            'reserved_cents' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
