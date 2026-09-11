<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notizen und Arbeitsstand des Kaeufers zu einem gekauften Lead.
 *
 * Beides haengt am Kaufbeleg und nicht am Lead: Es ist die Sicht eines Kaeufers
 * auf seinen Kauf. Zwei Kaeufer desselben geteilten Leads fuehren getrennte
 * Notizen, und `leads.lead_state` bleibt die einzige Zustandsspalte eines Leads
 * (Architekturleitsatz 1). `buyer_status` ist ausdruecklich eine Merkhilfe fuer
 * den Kaeufer und beeinflusst die Abrechnung nicht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table): void {
            $table->text('buyer_notes')->nullable()->after('buyer_feedback_at');
            $table->string('buyer_status', 32)->nullable()->after('buyer_notes');
        });
    }

    public function down(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table): void {
            $table->dropColumn(['buyer_notes', 'buyer_status']);
        });
    }
};
