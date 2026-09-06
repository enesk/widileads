<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-014: Veroeffentlichte Funnel-Versionen.
 *
 * Jede Veroeffentlichung schreibt einen unveraenderlichen JSON-Snapshot der
 * kompletten Struktur. Die oeffentliche Auslieferung liest ausschliesslich diese
 * Snapshots -- sonst wuerde eine Aenderung am Entwurf die laufende Strecke
 * veraendern und Leads entstuenden mit Angaben, die der Endkunde nie gesehen hat.
 *
 * funnels.current_version_id zeigt auf die zuletzt veroeffentlichte Version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->timestamp('published_at');
            // Der Nutzer darf gehen, die Version bleibt nachvollziehbar.
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['funnel_id', 'version']);
            $table->index(['funnel_id', 'published_at']);
        });

        Schema::table('funnels', function (Blueprint $table) {
            $table->foreignId('current_version_id')
                ->nullable()
                ->after('status')
                ->constrained('funnel_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->dropConstrainedForeignKey('current_version_id');
        });

        Schema::dropIfExists('funnel_versions');
    }
};
