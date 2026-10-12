<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-ONBOARD-014: Welche Mail der Onboarding-Strecke ein Workspace schon
 * bekommen hat.
 *
 * Die Tabelle ist nicht das Protokoll, sie ist die Zusicherung: Der
 * Unique-Index (tenant_id, key) ist der Idempotenzschluessel der Strecke.
 * Der Job legt die Zeile an, BEVOR er die Mail dem Mailer uebergibt, und
 * setzt `sent_at` erst danach. Daraus folgt beides, was das Ticket verlangt:
 * ein zweiter Lauf desselben Schluessels laeuft in den Index und schickt
 * nichts nach, waehrend ein Wiederholungsversuch nach einem Fehler des
 * Mailproviders (Zeile da, `sent_at` leer) die Mail noch nachreichen darf.
 *
 * `skipped_reason` traegt den Grund, aus dem nicht versendet wurde -- offene
 * Abbruchregel, Abmeldung, geloeschter Workspace. Die Zeile entsteht auch
 * dann, weil "uebersprungen" ein Ergebnis ist und kein Nichts: Der
 * Admin-Trichter (LP-ONBOARD-015) liest daraus, warum eine Mail fehlt.
 *
 * "Workspace" heisst in der Datenbank `tenant` -- die Spalte deshalb
 * `tenant_id`, der Tabellenname trotzdem so, wie das Ticket ihn nennt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_mails_sent', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Erlaubte Werte: App\Constants\OnboardingMailKey.
            $table->string('key', 40);

            // Leer, solange der Versand noch nicht durch ist, und bei allem,
            // was uebersprungen wurde.
            $table->timestamp('sent_at')->nullable();

            $table->string('skipped_reason', 60)->nullable();

            $table->timestamps();

            // Eine Mail je Workspace. Der Kern des Tickets.
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_mails_sent');
    }
};
