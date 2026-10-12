<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-ONBOARD-002: Wann der Einrichtungs-Wizard eines Workspaces vorbei ist.
 *
 * Beides liesse sich aus `tenant_onboarding_steps` herleiten -- der Wizard ist
 * fertig, wenn alle Pflichtschritte stehen. Die Spalten sind trotzdem da, weil
 * die Gate-Middleware sie bei jeder Anfrage liest: eine Spalte am schon
 * geladenen Mandanten statt drei Zeilen aus einer zweiten Tabelle.
 *
 * `onboarding_skipped_at` ist kein Duplikat davon: Ein Kaeufer, der den Wizard
 * ueberspringt, hat ihn nicht abgeschlossen. Der Wizard sperrt ihn danach
 * nicht mehr, die Checkliste bleibt aber offen -- und die Mailstrecke
 * (LP-ONBOARD-014) unterscheidet die beiden Faelle.
 *
 * Rein additiv, down() nimmt nur diese zwei Spalten zurueck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dateTime('onboarding_completed_at')->nullable()->after('payout_iban');
            $table->dateTime('onboarding_skipped_at')->nullable()->after('onboarding_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['onboarding_completed_at', 'onboarding_skipped_at']);
        });
    }
};
