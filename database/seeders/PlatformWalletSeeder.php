<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\WalletOwnerType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LP-WALLET-003: Das Wallet der Plattform.
 *
 * Es gibt genau eines, es gehoert keinem Mandanten (`owner_id = null`) und dort
 * landen Provisionen, Erstattungen und Korrekturen. Weil ein Unique-Index in
 * MySQL mehrere NULL-Werte zulaesst, sichert dieser Seeder die Einmaligkeit --
 * er ist idempotent und darf beliebig oft laufen.
 *
 * Teil des DatabaseSeeder, weil ohne dieses Wallet keine einzige Provision
 * gebucht werden kann; es ist Grundausstattung, keine Geschaeftsentscheidung.
 *
 * Bewusst ueber den Query Builder statt ueber ein Model: Das Wallet-Model
 * (LP-WALLET-004) gibt es zum Zeitpunkt dieser Migration noch nicht, und der
 * Seeder soll auch ohne es laufen.
 */
class PlatformWalletSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('wallets')
            ->where('owner_type', WalletOwnerType::PLATFORM->value)
            ->whereNull('owner_id')
            ->exists();

        if ($exists) {
            return;
        }

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
}
