<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-POSTPAID-005: Bereits verarbeitete Stripe-Ereignisse.
 *
 * Stripe liefert jedes Ereignis mindestens einmal -- und bei einer langsamen
 * oder abgebrochenen Antwort eben zweimal. Fuer die Postpaid-Ereignisse ist das
 * kein Schoenheitsfehler: Ein zweites Mal verarbeitete Ruecklastschrift wuerde
 * die Gebuehr doppelt buchen, ein zweites Mal verarbeiteter Mandatswiderruf die
 * Rueckstufung doppelt anstossen.
 *
 * Deshalb eine Tabelle und kein Cache-Eintrag: Der Cache laeuft hier auf dem
 * Dateitreiber, wird beim Ausrollen geleert und gilt nicht ueber mehrere
 * Server. Eine Zeile mit Unique-Index auf der Ereigniskennung haelt genau so
 * lange wie die Buchung, die sie schuetzt.
 *
 * Die Tabelle waechst langsam (wenige Ereignisse je Kaeufer und Woche) und wird
 * bewusst nicht automatisch geleert: Sie ist zugleich der Nachweis, welches
 * Ereignis wann verarbeitet wurde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();

            // Die Ereigniskennung von Stripe (evt_...). Der Unique-Index ist
            // die eigentliche Absicherung: Der zweite Einfuegeversuch
            // scheitert, und genau daran erkennt der Controller die
            // Wiederholung.
            $table->string('event_id')->unique();

            // Ereignisart (payment_method.detached, mandate.updated, ...), nur
            // zur Nachvollziehbarkeit.
            $table->string('type', 100);

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
