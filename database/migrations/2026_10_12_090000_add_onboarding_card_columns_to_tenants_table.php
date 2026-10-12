<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-ONBOARD-012: Die zwei Zustaende der Aktivierungskarte im Dashboard.
 *
 * `onboarding_card_hidden_until` traegt das Ende der Ausblendung: "Ausblenden"
 * ist bewusst kein endgueltiges Wegklicken, sondern sieben Tage Ruhe -- danach
 * erscheint die Karte wieder, solange der Kaeufer nicht aktiviert ist.
 *
 * `onboarding_card_done_seen_at` ist der Gegenpart dazu und haelt fest, dass
 * der Abschluss-Zustand ("Du bist startklar.") gezeigt wurde. Er soll genau
 * einen Besuch lang stehen; ohne Zeitstempel wuesste die Karte beim naechsten
 * Aufruf nicht, dass sie ihre Runde schon gedreht hat.
 *
 * Beides gehoert an den Mandanten und nicht in `tenant_onboarding_steps`: Es
 * ist kein erledigter Schritt, sondern die Anzeige daneben -- und die
 * Gate-Middleware und der View-Composer haben den Mandanten ohnehin schon
 * geladen.
 *
 * Rein additiv, down() nimmt nur diese zwei Spalten zurueck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dateTime('onboarding_card_hidden_until')->nullable()->after('onboarding_skipped_at');
            $table->dateTime('onboarding_card_done_seen_at')->nullable()->after('onboarding_card_hidden_until');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['onboarding_card_hidden_until', 'onboarding_card_done_seen_at']);
        });
    }
};
