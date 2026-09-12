<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-POSTPAID-003: Hinterlegte Zahlungsmittel eines Postpaid-Kaeufers.
 *
 * Die Zeile ist ein Verweis auf Stripe und kein Zahlungsmittel: Es stehen hier
 * ausschliesslich die Kennungen des Anbieters sowie die vier letzten Stellen
 * und die Kartenmarke zur Wiedererkennung im Portal. Vollstaendige IBAN oder
 * Kartennummer beruehren diese Anwendung nie -- sie entstehen im SetupIntent
 * direkt bei Stripe (LP-POSTPAID-005).
 *
 * Das Zahlungsmittel haengt am Wallet und nicht am Mandanten, aus demselben
 * Grund wie der Kreditrahmen: Eingezogen wird die Forderung eines Geldtopfs.
 *
 * `mandate_accepted_at` und `mandate_ip` belegen die Erteilung des
 * SEPA-Lastschriftmandats. Ohne diesen Nachweis ist eine Lastschrift
 * angreifbar, deshalb wird er zum Zeitpunkt der Zustimmung festgeschrieben und
 * danach nicht mehr geaendert.
 *
 * Bewusst KEIN Unique-Index auf (wallet_id, is_default): MariaDB kennt keinen
 * partiellen Unique-Index, ein voller wuerde auch mehrere `false`-Zeilen
 * verbieten. Dass es je Wallet hoechstens ein aktives Standardmittel gibt,
 * sichert das Model (LP-POSTPAID-005) -- so auch im Ticket vorgegeben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();

            // Erlaubte Werte: App\Constants\PaymentMethodType. Als String, wie
            // ueberall im Funnel Builder.
            $table->string('type', 20);

            // Zahlungsanbieter, derzeit ausschliesslich 'stripe'. Die Spalte
            // steht trotzdem hier, weil die Kennungen daneben nur zusammen mit
            // dem Anbieter eindeutig sind.
            $table->string('provider', 30)->default('stripe');

            // Kennungen beim Anbieter: Kunde, Zahlungsmittel und -- bei
            // SEPA -- das Mandat.
            $table->string('provider_customer_id');
            $table->string('provider_payment_method_id');
            $table->string('provider_mandate_id')->nullable();

            // Zur Wiedererkennung im Portal. `brand` gibt es nur bei Karten
            // (visa, mastercard), bei SEPA bleibt sie leer.
            $table->string('last4', 4);
            $table->string('brand', 30)->nullable();

            // Nachweis des erteilten Lastschriftmandats.
            $table->timestamp('mandate_accepted_at')->nullable();
            $table->string('mandate_ip', 45)->nullable();

            // Mittel, mit dem der Einzug versucht wird.
            $table->boolean('is_default')->default(false);

            // Erlaubte Werte: active | revoked | failed. Ein widerrufenes oder
            // dauerhaft gescheitertes Mittel bleibt stehen, weil Settlements
            // darauf verweisen.
            $table->string('status', 20)->default('active');

            $table->timestamps();

            // Ein Stripe-Zahlungsmittel gehoert genau einem Wallet; ein
            // zweiter Datensatz dazu waere ein Doppeleinzugsrisiko.
            $table->unique('provider_payment_method_id');

            // Die Liste im Portal und die Suche nach dem Standardmittel beim
            // Einzug.
            $table->index(['wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
