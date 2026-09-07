<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-073: Ein angeforderter Lead-Export.
 *
 * Der Export laeuft in der Warteschlange, nicht im Request: Ein Betreiber mit
 * fuenfstelligen Leadzahlen wuerde sonst in einen Timeout laufen, und zwar
 * genau dann, wenn der Export sich lohnt.
 *
 * Die Zeile ist zugleich der Beleg: Wer hat wann welche Spalten und welchen
 * Ausschnitt herausgezogen? Ein Export von Kontaktdaten ist der Vorgang, bei
 * dem am meisten Daten auf einmal das Haus verlassen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            // Erlaubte Werte: App\Constants\LeadExportStatus.
            $table->string('status', 20)->default('pending');

            // Gewaehlte Spalten und der Ausschnitt, auf den sie sich beziehen.
            $table->json('columns');
            $table->json('filters')->nullable();

            $table->unsignedInteger('row_count')->nullable();
            $table->string('disk', 40)->nullable();
            $table->string('path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_exports');
    }
};
