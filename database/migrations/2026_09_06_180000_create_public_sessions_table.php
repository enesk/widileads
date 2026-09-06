<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-021: Sitzungen und Ereignisse der oeffentlichen Funnel-Strecke.
 *
 * public_sessions haelt den Teilfortschritt, damit ein Endkunde einen Reload,
 * einen Netzabbruch oder den Wechsel aufs Handy uebersteht, ohne von vorn zu
 * beginnen. session_events protokolliert den Verlauf append-only -- daraus
 * entstehen spaeter die Abbruchquoten je Schritt (FB-034).
 *
 * Die Sitzung haengt an funnel_version_id, nicht am Funnel: Sie gehoert zu der
 * Fassung, die der Endkunde tatsaechlich gesehen hat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_version_id')->constrained('funnel_versions')->cascadeOnDelete();
            $table->char('token', 26)->unique();
            $table->json('answers')->nullable();
            $table->unsignedInteger('current_step')->nullable();
            $table->timestamp('started_at');
            // Marker fuer den Abbruchlauf: Wann hat der Endkunde zuletzt etwas getan?
            $table->timestamp('last_activity_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();

            $table->index(['funnel_version_id', 'started_at']);
            // Der Abbruchlauf sucht genau danach: offen und lange nichts getan.
            $table->index(['completed_at', 'abandoned_at', 'last_activity_at'], 'public_sessions_open_idx');
        });

        Schema::create('session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('public_sessions')->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedInteger('step_position')->nullable();
            $table->timestamp('created_at');

            $table->index(['session_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_events');
        Schema::dropIfExists('public_sessions');
    }
};
