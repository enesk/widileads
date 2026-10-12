<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Die Region als eigenes Kriterium (LP-ONBOARD-009).
 *
 * Der zweite Wizard-Schritt laesst den Kaeufer zwischen "ganz Deutschland" und
 * einem Umkreis waehlen. Beides steht hier als drei Spalten und nicht in den
 * vorhandenen `postal_prefixes`: Ein Umkreis von 50 Kilometern um 76133 sind
 * ueber tausend Postleitzahlen, und als Liste gespeichert waere die Angabe
 * weder lesbar noch wieder in einen Regler zu uebersetzen -- der Kaeufer
 * fände beim naechsten Oeffnen eine Praefixwolke statt seiner Wahl vor.
 *
 * `region` bleibt ein Text und wird kein Enum in der Datenbank: Das Projekt
 * haelt seine Aufzaehlungen in app/Constants (hier App\Constants\BuyerRegion),
 * und eine zweite Wahrheit im Schema muesste bei jedem neuen Fall migriert
 * werden.
 *
 * Ausgewertet wird der Umkreis noch nicht -- LeadMatcher filtert weiterhin
 * ueber `postal_prefixes`. Das ist Absicht und ein eigenes Ticket: Der Matcher
 * bedient Marktplatz und Autokauf gleichzeitig, und seine Regeln aendert man
 * nicht nebenbei in einem Wizard-Schritt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table): void {
            $table->string('region', 16)->nullable()->after('funnel_ids');
            $table->string('postal_code', 5)->nullable()->after('region');
            $table->unsignedSmallInteger('radius_km')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table): void {
            $table->dropColumn(['region', 'postal_code', 'radius_km']);
        });
    }
};
