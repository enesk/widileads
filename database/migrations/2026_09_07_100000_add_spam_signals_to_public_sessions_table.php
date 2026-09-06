<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-023: Spam-Signale einer oeffentlichen Sitzung.
 *
 * Die Signale werden erfasst, aber hier NICHT bewertet: Beweis vor Bewertung.
 * Ob aus einer verdaechtigen Einreichung ein ungueltiger Lead wird, entscheidet
 * der Pruefjob in FB-033 -- mit den Rohdaten vor sich statt mit einer bereits
 * getroffenen Entscheidung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_sessions', function (Blueprint $table) {
            $table->json('spam_signals')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('public_sessions', function (Blueprint $table) {
            $table->dropColumn('spam_signals');
        });
    }
};
