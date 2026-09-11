<?php

declare(strict_types=1);

use App\Constants\PayoutStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-WALLET-010: Auszahlungsanforderungen des Verkaeufers.
 *
 * Eine Anforderung haengt am Wallet und nicht am Mandanten: Ausgezahlt wird
 * ein Geldtopf, und derselbe Mandant kann als Kaeufer und als Verkaeufer
 * auftreten (WalletOwnerType). Der Verkaeufer steht ueber `wallets.owner_id`
 * daran.
 *
 * `iban_last4` ist eine Momentaufnahme und kein Verweis: Der Verkaeufer kann
 * seine Bankverbindung jederzeit aendern, die Liste der Auszahlungen muss aber
 * belegen, wohin damals ueberwiesen wurde. Die vollstaendige IBAN steht
 * verschluesselt am Mandanten (tenants.payout_iban) und wird hier bewusst
 * nicht kopiert -- ein zweiter Ort mit Bankdaten ist ein zweites Risiko.
 *
 * Abgebucht wird schon bei der Anforderung (App\Services\Wallet\PayoutService).
 * Deshalb ist der Datensatz kein blosser Antrag, sondern der Beleg zu einer
 * bereits gebuchten `payout`-Zeile im Ledger; eine Ablehnung bucht mit einer
 * `adjustment`-Zeile zurueck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();

            // bigint wie die Salden in `wallets`: eine Auszahlung kann so gross
            // sein wie der Saldo, aus dem sie kommt.
            $table->bigInteger('amount_cents');

            // Nur die letzte Vierergruppe der IBAN, zum Zeitpunkt der
            // Anforderung. Nullable, weil eine Anforderung aus der Admin-Sicht
            // auch ohne hinterlegte Bankverbindung nachvollziehbar bleiben
            // muss, wenn diese spaeter geloescht wird.
            $table->char('iban_last4', 4)->nullable();

            // Erlaubte Werte: App\Constants\PayoutStatus. Als String und nicht
            // als DB-Enum, wie ueberall im Funnel Builder.
            $table->string('status', 20)->default(PayoutStatus::REQUESTED->value);

            $table->timestamp('requested_at');

            // Gesetzt, sobald Enes ueberwiesen oder abgelehnt hat.
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            // Begruendung der Ablehnung, bei einer Auszahlung frei nutzbar
            // (Verwendungszweck, Belegnummer der Ueberweisung).
            $table->text('note')->nullable();

            $table->timestamps();

            // Die Arbeitsliste des Admins: offene Anforderungen, aelteste
            // zuerst.
            $table->index(['status', 'requested_at']);

            // Die Liste des Verkaeufers: seine Anforderungen, neueste zuerst.
            $table->index(['wallet_id', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
