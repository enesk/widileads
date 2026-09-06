<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-050: Registrierung und Freischaltung eines Kaeufer-Mandanten.
 *
 * Eigene Tabelle statt weiterer Spalten an `tenants`: die kaufmaennischen
 * Angaben und der Freischaltungsstand betreffen ausschliesslich Kaeufer, und
 * ein Betreiber-Mandant traegt sie nie. Genau eine Zeile je Mandant.
 *
 * Nicht zu verwechseln mit `buyer_profiles` aus FB-051 -- die tragen die
 * Kaufkriterien (welche Leads will der Kaeufer), diese Tabelle den Stammsatz
 * und die Freigabe (darf der Kaeufer ueberhaupt kaufen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_registrations', function (Blueprint $table) {
            $table->id();

            // Genau ein Registrierungssatz je Mandant.
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();

            // Erlaubte Werte: App\Constants\BuyerRegistrationStatus. Als String
            // und nicht als DB-Enum, damit ein neuer Zustand keine
            // Tabellenaenderung braucht.
            $table->string('status', 20)->default('pending');

            $table->string('company_name');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();

            // Vermittlerregister-Nummer nach Paragraf 34d GewO. Bewusst
            // optional: nicht jeder Kaeufer ist Versicherungsvermittler
            // (Vergleichsportale, Direktversicherer). Geprueft wird im
            // Einzelfall bei der Freischaltung.
            $table->string('broker_register_number')->nullable();

            $table->string('vat_id');

            // Zeitpunkt der Zustimmung zum Auftragsverarbeitungsvertrag. Er ist
            // der Nachweis und deshalb nicht nullable: ohne Zustimmung entsteht
            // kein Registrierungssatz.
            $table->timestamp('av_accepted_at');

            // Entscheidung des Plattform-Admins. Solange sie aussteht, sind
            // beide Felder leer.
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            // Pflicht bei Ablehnung, damit die Entscheidung nachvollziehbar
            // bleibt und dem Kaeufer mitgeteilt werden kann.
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Die Pruefliste des Plattform-Admins: offene Registrierungen,
            // aelteste zuerst.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_registrations');
    }
};
