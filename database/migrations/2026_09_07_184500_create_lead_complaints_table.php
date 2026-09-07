<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-058: Reklamation eines gekauften Leads.
 *
 * Der Kaeufer beantragt binnen der Reklamationsfrist, dass ein Lead als
 * `unerreichbar` oder `ungueltig` gilt. Der Antrag ist ein eigener Datensatz
 * und kein Feld am Lead: Er hat einen eigenen Lebenszyklus, eine Begruendung
 * und eine Entscheidung -- und bei einem geteilten Lead (FB-055) koennen
 * mehrere Kaeufer unabhaengig voneinander reklamieren.
 *
 * Er haengt am Kaufbeleg, nicht nur am Lead: Reklamiert wird ein Kauf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_purchase_id')->constrained('lead_purchases')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('buyer_tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Erlaubte Werte: App\Constants\ComplaintStatus.
            $table->string('status', 20)->default('pending');

            // Der beantragte Zustand -- `unerreichbar` oder `ungueltig`
            // (App\Constants\LeadState).
            $table->string('requested_state', 20);

            // Pflichtangabe. Ohne Begruendung ist ein Antrag nicht pruefbar.
            $table->text('reason');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();

            // Ein Kauf wird hoechstens einmal reklamiert.
            $table->unique('lead_purchase_id');

            // Die Pruefliste: offene Antraege, aelteste zuerst.
            $table->index(['status', 'created_at']);

            // Die Reklamationsquote je Kaeufer (FB-060) liest genau danach.
            $table->index(['buyer_tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_complaints');
    }
};
