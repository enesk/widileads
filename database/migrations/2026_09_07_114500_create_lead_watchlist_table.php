<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-053: Merkliste eines Kaeufers im Marktplatz.
 *
 * Ein Kaeufer markiert Leads, die er sich vormerken will, ohne sie schon zu
 * kaufen. Die Merkliste sagt nichts ueber Reservierung oder Kauf aus -- sie ist
 * eine private Notiz und hat auf den Zustand des Leads keinerlei Wirkung.
 *
 * Die Tabelle heisst im Singular, weil sie eine Liste ist (wie lead_state_log
 * und credit_ledger).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_watchlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            // Ein Lead steht je Kaeufer hoechstens einmal auf der Merkliste.
            $table->unique(['tenant_id', 'lead_id']);

            // Die Marktplatzliste blendet die Merkliste je Kaeufer ein.
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_watchlist');
    }
};
