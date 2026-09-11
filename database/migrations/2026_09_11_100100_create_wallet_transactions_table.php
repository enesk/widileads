<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-WALLET-003: Das Journal des Wallets.
 *
 * Append-only nach demselben Muster wie credit_ledger (FB-052), lead_state_log
 * (FB-030) und audit_logs (FB-005): Eintraege werden nie geaendert und nie
 * geloescht. Deshalb gibt es `created_at`, aber weder `updated_at` noch
 * SoftDeletes -- eine Zeitspalte, die eine Aenderung anzeigen koennte, waere
 * eine Einladung, doch eine zu machen. Durchgesetzt wird das zusaetzlich im
 * Model (LP-WALLET-004).
 *
 * Jede Buchung haelt den Stand BEIDER Salden nach ihrer Wirkung fest
 * (`balance_after_cents`, `reserved_after_cents`). Damit ist ein Auszug ohne
 * Nachrechnen lesbar und die Konsistenzpruefung (LP-WALLET-015) kann Journal
 * und Wallet gegeneinander halten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();

            // Erlaubte Werte: App\Constants\WalletTransactionType.
            $table->string('type', 20);

            // Vorzeichenbehaftet, damit der Saldo eine simple Summe ist. Welches
            // Vorzeichen je Buchungsart zulaessig ist, entscheidet
            // WalletTransactionType::allowsAmount().
            $table->bigInteger('amount_cents');

            // Stand der beiden Salden nach dieser Buchung.
            $table->bigInteger('balance_after_cents');
            $table->bigInteger('reserved_after_cents');

            // Beleg der Buchung (morph, ohne Constraint): der Leadkauf, die
            // Bestellung der Aufladung, die Auszahlungsanforderung.
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // Klammer gegen Doppelbuchungen bei wiederholter Zustellung
            // (Stripe-Webhook, Queue-Retry). Null heisst: keine Wiederholung zu
            // erwarten; NULL-Werte schliesst ein Unique-Index in MySQL nicht aus.
            $table->string('idempotency_key')->nullable()->unique();

            // Deutscher Klartext, so wie er dem Kaeufer im Transaktionsverlauf
            // angezeigt wird (LP-WALLET-011).
            $table->string('description');

            // Zusatzangaben der Buchung (Provisionssatz, Lead-Kennung, Grund
            // einer Korrektur).
            $table->json('meta')->nullable();

            // Nur bei manuellen Korrekturen gesetzt: der Admin, der gebucht hat.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->nullable();

            // Der Auszug eines Wallets liest genau danach.
            $table->index(['wallet_id', 'created_at']);

            // Weg vom Beleg zur Buchung: welche Buchungen gehoeren zu diesem Kauf?
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
