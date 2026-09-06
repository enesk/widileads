<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-051: Kaufkriterien eines Kaeufer-Mandanten.
 *
 * Ein Profil je Kaeufer. Es beantwortet eine einzige Frage: Welche Leads will
 * dieser Kaeufer sehen? Ausgewertet wird es vom LeadMatcher -- im Marktplatz
 * (FB-053) und beim Autokauf (FB-056) mit derselben Funktion.
 *
 * Nicht zu verwechseln mit `buyer_registrations` aus FB-050: die tragen den
 * Stammsatz und die Freigabe (darf der Kaeufer ueberhaupt kaufen), diese
 * Tabelle die Auswahl (was will er kaufen).
 *
 * Ein leeres Filterfeld heisst "keine Einschraenkung", nicht "nichts". Ein
 * frisches Profil sieht also alles -- die Alternative waere ein Kaeufer, der
 * nach dem Anlegen einen leeren Marktplatz sieht und den Fehler bei uns sucht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_profiles', function (Blueprint $table) {
            $table->id();

            // Genau ein Profil je Mandant.
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();

            // Liste von Funnel-IDs. Leer: alle Funnels.
            $table->json('funnel_ids')->nullable();

            // Liste von Postleitzahl-Praefixen, z. B. ["76","77","68"].
            // Leer: keine regionale Einschraenkung.
            $table->json('postal_prefixes')->nullable();

            // Feldschluessel auf erlaubte Antworten, z. B.
            // {"tierart":["hund","katze"]}. Leer: keine Einschraenkung.
            $table->json('answer_filters')->nullable();

            // Mindestpunktzahl der Qualifizierung. null: keine Untergrenze.
            // Bewusst signed: die Punktzahl eines Funnels kann negativ sein,
            // wenn Optionen Abzuege tragen (FB-013).
            $table->integer('min_score')->nullable();

            // Hoechstzahl automatischer Kaeufe je Kalendertag (FB-056).
            // null: keine Begrenzung.
            $table->unsignedInteger('daily_limit')->nullable();

            // Kauft die Plattform passende Leads von sich aus (FB-056)?
            $table->boolean('auto_buy')->default(false);

            // Adresse fuer Benachrichtigungen ueber gekaufte Leads. Ohne
            // Angabe wird die Adresse aus der Registrierung verwendet.
            $table->string('notify_email')->nullable();

            $table->timestamps();

            // Der Autokauf-Job sucht genau danach: Profile mit aktivem Autokauf.
            $table->index('auto_buy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_profiles');
    }
};
