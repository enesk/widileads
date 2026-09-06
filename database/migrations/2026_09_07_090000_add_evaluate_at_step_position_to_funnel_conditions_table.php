<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-012a: Wann eine Verzweigungsregel ausgewertet wird.
 *
 * Bisher galt implizit: Eine Regel greift beim Verlassen des Schritts, in dem
 * ihre Ausgangsfrage steht. Damit laesst sich der einfachste reale Fall des
 * Referenzfunnels nicht abbilden -- "anderes Tier ueberspringt Rasse und
 * Groesse, aber nicht das Alter", wenn die Tierart in Schritt 1 und die Rasse in
 * Schritt 3 steht.
 *
 * Die neue Spalte trennt beides: source_question_id sagt, WAS geprueft wird,
 * evaluate_at_step_position sagt, WANN. Ohne Wert gilt weiterhin der Schritt der
 * Ausgangsfrage -- bestehende Funnels aendern ihr Verhalten nicht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_conditions', function (Blueprint $table) {
            $table->unsignedInteger('evaluate_at_step_position')->nullable()->after('target_step_id');

            $table->index(['funnel_id', 'evaluate_at_step_position'], 'funnel_conditions_evaluate_idx');
        });
    }

    public function down(): void
    {
        Schema::table('funnel_conditions', function (Blueprint $table) {
            $table->dropIndex('funnel_conditions_evaluate_idx');
            $table->dropColumn('evaluate_at_step_position');
        });
    }
};
