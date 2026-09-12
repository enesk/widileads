<?php

declare(strict_types=1);

use App\Constants\PaymentMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-POSTPAID-003: Aufschlag und Zahlungsmodus als Snapshot am Kaufbeleg.
 *
 * Beide Spalten werden zum Kaufzeitpunkt festgeschrieben und danach nie
 * geaendert -- wie schon `commission_percent` seit LP-WALLET-003
 * (Architekturleitsatz 4). Eine spaetere Aenderung des Aufschlagsatzes oder
 * eine Rueckstufung des Kaeufers darf laufende Kaeufe nicht rueckwirkend
 * verteuern oder verbilligen.
 *
 * `surcharge_cents` steht neben `price_cents` und ist nicht darin enthalten:
 * Der Verkaeufererloes und die Provision rechnen weiter gegen den Leadpreis,
 * der Aufschlag geht vollstaendig an die Plattform (LP-POSTPAID-007). Es gilt
 * damit weiterhin price_cents = commission_cents + seller_net_cents, und der
 * Kaeufer belastet wird mit price_cents + surcharge_cents.
 *
 * Bestandszeilen sind Prepaid-Kaeufe ohne Aufschlag; die Vorgabewerte 0 und
 * `prepaid` treffen sie korrekt, eine Nachpflege entfaellt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->integer('surcharge_cents')->default(0)->after('seller_net_cents');

            // Erlaubte Werte: App\Constants\PaymentMode.
            $table->string('payment_mode', 20)->default(PaymentMode::PREPAID->value)->after('surcharge_cents');
        });
    }

    public function down(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->dropColumn(['surcharge_cents', 'payment_mode']);
        });
    }
};
