<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-057: Rueckmeldung des Kaeufers zu einem gekauften Lead.
 *
 * Die Spalten haengen am Kaufbeleg, nicht am Lead. Das ist keine Kleinigkeit:
 * `leads.lead_state` bleibt die einzige Zustandsspalte eines Leads
 * (Architekturleitsatz 1), und zwei Kaeufer desselben geteilten Leads (FB-055)
 * duerfen unterschiedlich urteilen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table) {
            // Erlaubte Werte: App\Constants\BuyerLeadFeedback. Null heisst:
            // noch keine Rueckmeldung.
            $table->string('buyer_feedback', 20)->nullable()->after('purchased_at');
            $table->timestamp('buyer_feedback_at')->nullable()->after('buyer_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->dropColumn(['buyer_feedback', 'buyer_feedback_at']);
        });
    }
};
