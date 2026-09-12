<?php

declare(strict_types=1);

use App\Constants\SettlementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-POSTPAID-003: Einzug des offenen Betrags eines Postpaid-Kaeufers.
 *
 * Ein Settlement fasst den zum Stichtag offenen Betrag eines Wallets zu einer
 * Forderung zusammen und zieht sie ueber den Zahlungsanbieter ein
 * (LP-POSTPAID-008). Es ist damit das Gegenstueck zu `payout_requests` auf der
 * Verkaeuferseite und haengt aus demselben Grund am Wallet.
 *
 * Der Weg ist mehrstufig (App\Constants\SettlementStatus), weil eine
 * Lastschrift Tage nach der Gutschrift noch zurueckgegeben werden kann:
 * pending -> processing -> paid, dazwischen failed, retry_pending und
 * returned. `attempts` und `next_attempt_at` steuern den zweiten Versuch.
 *
 * `payment_method_id` ist nullable und nullOnDelete: Die Forderung besteht
 * weiter, auch wenn der Kaeufer sein Zahlungsmittel entfernt hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();

            // bigint wie die Salden in `wallets`: die Forderung kann so gross
            // sein wie der ausgeschoepfte Rahmen.
            $table->bigInteger('amount_cents');

            // Erlaubte Werte: App\Constants\SettlementStatus. Als String, wie
            // ueberall im Funnel Builder.
            $table->string('status', 20)->default(SettlementStatus::PENDING->value);

            // Was den Einzug ausgeloest hat: der Wochentermin (scheduled), das
            // Ueberschreiten der Schwelle (threshold) oder der Admin (manual).
            $table->string('trigger', 20);

            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();

            // Kennung der Zahlung beim Anbieter; traegt die Zuordnung des
            // Webhooks zum Settlement.
            $table->string('provider_payment_intent_id')->nullable();

            // Zahl der bisherigen Einzugsversuche und Zeitpunkt des naechsten.
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Klartextmeldung des Anbieters (insufficient_funds,
            // debit_not_authorized); geht in die Benachrichtigung des Kaeufers
            // ein.
            $table->string('failure_reason')->nullable();

            // Verweis auf den erzeugten Rechnungsbeleg.
            $table->string('invoice_reference')->nullable();

            $table->timestamps();

            // Der offene Betrag eines Kaeufers und die Arbeitsliste des
            // Einzugs.
            $table->index(['wallet_id', 'status']);

            // Der Scheduler sucht faellige Wiederholungen ueber alle Wallets.
            $table->index(['status', 'next_attempt_at']);

            // Die Zuordnung des Webhooks. Kein Unique-Index: gescheiterte
            // Versuche koennen dieselbe Kennung ein zweites Mal tragen.
            $table->index('provider_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
