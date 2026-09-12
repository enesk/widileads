<?php

declare(strict_types=1);

use App\Constants\PaymentMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-POSTPAID-003: Zahlungsmodus und Kreditrahmen am Wallet.
 *
 * Der Rahmen gehoert zum Geldtopf und nicht zum Benutzer oder Mandanten:
 * Derselbe Mandant kann als Kaeufer und als Verkaeufer auftreten und haelt
 * dann zwei getrennte Wallets (WalletOwnerType). Nur das Kauf-Wallet darf ins
 * Minus laufen, also steht der Rahmen genau dort.
 *
 * `credit_limit_cents` ist der Betrag, um den `balance_cents` negativ werden
 * darf; die Deckungspruefung im WalletService (LP-POSTPAID-004) rechnet damit.
 * Bei `payment_mode = prepaid` bleibt er wirkungslos, wird aber nicht
 * geloescht -- nach einer Rueckstufung soll der frueher gewaehrte Rahmen
 * sichtbar bleiben.
 *
 * `purchase_blocked` ist die harte Sperre nach einer Zahlungsstoerung
 * (LP-POSTPAID-009) und bewusst unabhaengig vom Modus: Ein zurueckgestufter
 * Kaeufer soll auch mit frischem Guthaben nicht weiterkaufen koennen, solange
 * seine Forderung offen ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            // Erlaubte Werte: App\Constants\PaymentMode. Als String und nicht
            // als DB-Enum, wie ueberall im Funnel Builder (wallets.owner_type,
            // lead_purchases.status): ein weiterer Modus soll ohne
            // Tabellenaenderung moeglich bleiben.
            $table->string('payment_mode', 20)->default(PaymentMode::PREPAID->value)->after('currency');

            // Betrag, um den der Saldo hoechstens ins Minus laufen darf.
            // bigint wie die Salden daneben.
            $table->bigInteger('credit_limit_cents')->default(0)->after('reserved_cents');

            // Sperre nach Zahlungsstoerung: kein Leadkauf, unabhaengig vom
            // Guthaben.
            $table->boolean('purchase_blocked')->default(false)->after('credit_limit_cents');

            // Belegspalten der Freischaltung. `postpaid_enabled_by` ist der
            // entscheidende Admin; nullOnDelete, weil die Freischaltung auch
            // dann nachvollziehbar bleiben muss, wenn das Konto spaeter
            // geloescht wird.
            $table->timestamp('postpaid_enabled_at')->nullable()->after('purchase_blocked');
            $table->foreignId('postpaid_enabled_by')->nullable()->after('postpaid_enabled_at')
                ->constrained('users')->nullOnDelete();

            // Belegspalten der Rueckstufung. Der Grund ist Freitext, weil er
            // sowohl vom System (gescheiterter Einzug) als auch von Hand
            // gesetzt wird.
            $table->timestamp('postpaid_disabled_at')->nullable()->after('postpaid_enabled_by');
            $table->string('postpaid_disabled_reason')->nullable()->after('postpaid_disabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['postpaid_enabled_by']);
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn([
                'payment_mode',
                'credit_limit_cents',
                'purchase_blocked',
                'postpaid_enabled_at',
                'postpaid_enabled_by',
                'postpaid_disabled_at',
                'postpaid_disabled_reason',
            ]);
        });
    }
};
