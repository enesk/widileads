<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-POSTPAID-014: SEPA-Vorabankuendigung je Einzug.
 *
 * Bei der SEPA-Basislastschrift muss der Zahlungsempfaenger den Zahler vor der
 * Belastung ankuendigen -- mit Betrag und Belastungsdatum. `payment_methods`
 * belegt mit `mandate_accepted_at` nur die Erteilung des Mandats, also die
 * Erlaubnis; die Ankuendigung faellt dagegen bei jedem einzelnen Einzug erneut
 * an und gehoert damit an das Settlement.
 *
 * Der Einzug wird dadurch zweistufig: Das Settlement entsteht als `pending`
 * mit angekuendigtem Belastungsdatum (`charge_due_at`), die Ankuendigung geht
 * raus (`prenotified_at`), und erst nach Ablauf der Frist wird belastet.
 *
 * Beide Spalten sind nullable, weil sie fuer Kartenzahlungen nie gefuellt
 * werden: Dort gibt es keine Ankuendigungspflicht
 * (App\Constants\PaymentMethodType::requiresPrenotification()).
 *
 * Aenderungsmigration statt Umschreiben der Anlage-Migration, obwohl die
 * Tabelle noch nicht ausgerollt ist: Die Anlage ist committet, und eine
 * nachtraeglich veraenderte Migration laeuft auf keiner Datenbank noch einmal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Zeitpunkt, zu dem die Vorabankuendigung an den Kaeufer
            // verschickt wurde. Null heisst: noch nicht angekuendigt, es darf
            // nicht belastet werden.
            $table->timestamp('prenotified_at')->nullable()->after('next_attempt_at');

            // Das dem Kaeufer angekuendigte Belastungsdatum. Es ist eine
            // Zusage und kein Richtwert: Vor diesem Zeitpunkt darf nicht
            // eingezogen werden, auch wenn der Scheduler frueher laeuft.
            $table->timestamp('charge_due_at')->nullable()->after('prenotified_at');

            // Die Arbeitsliste des Einzugs fragt nach faelligen, bereits
            // angekuendigten Forderungen.
            $table->index(['status', 'charge_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropIndex(['status', 'charge_due_at']);
            $table->dropColumn(['prenotified_at', 'charge_due_at']);
        });
    }
};
