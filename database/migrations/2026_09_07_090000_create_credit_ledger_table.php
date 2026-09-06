<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-052: Guthabenkonto der Kaeufer.
 *
 * Ein Journal, kein Kontostand: Der Saldo eines Mandanten ist die Summe seiner
 * Buchungen. Ein gepflegter Zaehler waere schneller zu lesen, aber er kann von
 * der Historie abweichen -- und dann ist nicht mehr feststellbar, welcher der
 * beiden Werte stimmt. Bei Geld ist die Historie die Wahrheit.
 *
 * Die Tabelle heisst im Singular, weil sie ein Journal ist und keine Sammlung
 * gleichrangiger Datensaetze -- so auch lead_state_log (FB-030) und so im
 * Datenmodell (Teil 2).
 *
 * Append-only: Eintraege werden nie geaendert und nie geloescht. Durchgesetzt
 * wird das in App\Models\CreditLedgerEntry nach demselben Muster wie AuditLog
 * (FB-005) und LeadStateLog (FB-030).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Erlaubte Werte: App\Constants\CreditLedgerType.
            $table->string('type', 20);

            // Vorzeichenbehaftet, damit der Saldo eine simple Summe ist:
            // Kauf und Gutschrift positiv, Abbuchung negativ, Korrektur beides.
            $table->integer('credits');

            // Bezahlter Betrag in Cent. Nur bei Buchungen, hinter denen
            // tatsaechlich Geld steht -- eine Abbuchung von Guthaben kostet
            // nichts extra und traegt hier null.
            $table->unsignedBigInteger('amount_cents')->nullable();

            // Beleg der Buchung: die Bestellung beim Kauf, der Lead-Kauf bei
            // der Abbuchung, die Reklamation bei der Gutschrift.
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamp('created_at')->nullable();

            // Der Saldo liest genau danach.
            $table->index(['tenant_id', 'created_at']);

            // Ein Beleg traegt je Buchungsart hoechstens eine Buchung. Das ist
            // die Idempotenz des Stripe-Webhooks auf Datenbankebene: Wird
            // dasselbe Ereignis erneut zugestellt -- und Stripe stellt erneut
            // zu -- laeuft der zweite Versuch in diesen Index statt Guthaben ein
            // zweites Mal gutzuschreiben. Korrekturen tragen keinen Beleg;
            // NULL-Werte schliesst ein Unique-Index in MySQL nicht aus, mehrere
            // Korrekturen bleiben also moeglich.
            $table->unique(['type', 'reference_type', 'reference_id'], 'credit_ledger_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_ledger');
    }
};
