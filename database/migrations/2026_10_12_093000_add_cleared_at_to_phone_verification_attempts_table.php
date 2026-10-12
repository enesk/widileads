<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-ONBOARD-022: Der Weg, einen Versuch aus den Rate-Limits zu nehmen.
 *
 * Die Limits der Rufnummernbestaetigung zaehlen die Zeilen dieser Tabelle
 * (siehe PhoneVerificationService). Hebt der Support die Sperre einer Nummer
 * auf, darf das Protokoll deshalb nicht geloescht und auch nicht auf `failed`
 * umgeschrieben werden -- beides wuerde die Historie faelschen, die der
 * Support spaeter noch braucht ("wann ging da welche SMS raus?").
 *
 * Stattdessen eine Markierung: `cleared_at` sagt, dass diese Zeile nicht mehr
 * gegen ein Limit zaehlt. Der Versuch bleibt lesbar, der Zaehler ist frei, und
 * dass jemand nachgeholfen hat, steht in der Zeile selbst.
 *
 * Kein eigener Index: Die Limitabfrage laeuft weiter ueber
 * (phone_e164, created_at); `cleared_at` ist nur ein Filter auf dem schon
 * eingegrenzten Fenster von hoechstens einer Handvoll Zeilen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_verification_attempts', function (Blueprint $table) {
            $table->timestamp('cleared_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('phone_verification_attempts', function (Blueprint $table) {
            $table->dropColumn('cleared_at');
        });
    }
};
