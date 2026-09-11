<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drei Kriterien, die die Portalfassung der Kaufkriterien ergaenzt (Portal
 * Phase 1): Hoechstpreis, Wochenbudget und der Zeitpunkt der Benachrichtigung.
 *
 * Alle drei sind Kriterien des Kaeufers und gehoeren damit zum Profil. Gelesen
 * werden sie von LeadMatcher (Preis), vom Autokauf (Budget) und vom Versand
 * (Zeitpunkt) -- diese Auswertung ist noch nicht gebaut, die Spalten stehen
 * hier zuerst.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('max_price_cents')->nullable()->after('min_score');
            $table->unsignedInteger('weekly_budget_cents')->nullable()->after('daily_limit');
            $table->string('notify_interval', 16)->nullable()->after('notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table): void {
            $table->dropColumn(['max_price_cents', 'weekly_budget_cents', 'notify_interval']);
        });
    }
};
