<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-084 (Ticket #14): Merker fuer die Erinnerung 24 h vor Fristende.
 *
 * Der Zeitstempel haengt am Lead und nicht am Kaufbeleg, weil die Frist dem
 * Lead gehoert (siehe FB-055): Bei einem geteilten Lead geht die Erinnerung an
 * alle Kaeufer, aber nur einmal -- ein zweiter Lauf sieht den gesetzten
 * Merker und ueberspringt den Lead.
 *
 * Ein eigener Index waere hier Ballast: Der Lauf sucht ueber
 * (contact_status, deadline_at) aus FB-082 und prueft `reminder_sent_at` erst
 * auf der so bereits eingegrenzten Menge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable()->after('phone_revealed_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
