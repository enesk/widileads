<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-054: Der Kauf eines Leads.
 *
 * Zwei Dinge in einem Thema: der Kaufbeleg und die Reservierung, die ihm
 * vorausgeht.
 *
 * `lead_purchases` ist der Beleg dafuer, dass ein Kaeufer diesen Lead erworben
 * hat -- und damit die Grundlage der Entscheidung, wer Kontaktdaten im Klartext
 * sieht (LeadContactResolver, FB-032). Er wird nach dem Anlegen nicht mehr
 * geaendert.
 *
 * `leads.reserved_by` und `leads.reserved_until` halten fest, wer einen Lead
 * gerade in Arbeit hat. Sie sind die Ergaenzung zum Zustand `reserviert`: Der
 * Zustand sagt, DASS reserviert ist, diese Spalten sagen, fuer wen und wie
 * lange. Ohne sie waere nach einem Absturz nicht feststellbar, wessen
 * Reservierung verfaellt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('buyer_tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Preis in der kleinsten Waehrungseinheit, festgeschrieben zum
            // Zeitpunkt des Kaufs. Er aendert sich danach nie -- auch nicht,
            // wenn der Funnelpreis spaeter angepasst wird (Architekturleitsatz 4).
            $table->unsignedBigInteger('price_cents');

            // Waehrung des Preises. Aus demselben Grund wie in credit_ledger
            // (FB-052a): ein Betrag ohne Waehrung ist in einem
            // mehrwaehrungsfaehigen System nicht rekonstruierbar.
            $table->char('currency', 3);

            $table->timestamp('purchased_at');
            $table->timestamps();

            // Ein Kaeufer kauft denselben Lead hoechstens einmal. Dass ein Lead
            // heute ueberhaupt nur einmal verkauft wird, sichert die
            // Zustandsmaschine -- aus `verkauft` fuehrt kein Weg zurueck nach
            // `reserviert`. FB-055 (Mehrfachverkauf) baut auf genau diesem
            // Index auf.
            $table->unique(['lead_id', 'buyer_tenant_id']);

            // "Meine Leads" des Kaeufers (FB-057) und die Abrechnung (FB-059)
            // lesen genau danach.
            $table->index(['buyer_tenant_id', 'purchased_at']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('reserved_by')->nullable()->after('lead_state')
                ->constrained('tenants')->nullOnDelete();
            $table->timestamp('reserved_until')->nullable()->after('reserved_by');

            // Der Scheduler sucht genau danach: abgelaufene Reservierungen.
            $table->index(['reserved_until']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['reserved_until']);
            $table->dropConstrainedForeignId('reserved_by');
            $table->dropColumn('reserved_until');
        });

        Schema::dropIfExists('lead_purchases');
    }
};
