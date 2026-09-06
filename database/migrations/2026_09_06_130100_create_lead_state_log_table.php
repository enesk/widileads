<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-030: Unveraenderliches Protokoll aller Lead-Zustandswechsel.
 *
 * Ein Eintrag je Uebergang, geschrieben in derselben Transaktion wie die
 * Zustandsaenderung selbst. Es gibt kein updated_at, weil ein Eintrag nie
 * geaendert wird.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_state_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();

            // Nullable, damit FB-031 den Eingang eines Leads als ersten
            // Eintrag ohne Vorzustand protokollieren kann.
            $table->string('from_state', 20)->nullable();
            $table->string('to_state', 20);
            $table->string('reason', 50);

            // Null bei automatischen Uebergaengen durch Jobs oder Scheduler.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['lead_id', 'created_at']);
            $table->index(['to_state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_state_log');
    }
};
