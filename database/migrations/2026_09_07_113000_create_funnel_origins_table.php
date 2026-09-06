<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-025: Erlaubte Einbettungs-Herkuenfte je Funnel.
 *
 * Ohne Allowlist kann jede fremde Seite einen Funnel in ihre eigene einbetten
 * und Leads unter ihrem Namen sammeln -- der Betreiber sieht nur, dass Anfragen
 * kommen, nicht von wo.
 *
 * Gespeichert wird die normalisierte Herkunft ("https://host[:port]"), nicht
 * eine ganze URL: Ein Pfad spielt fuer die Einbettung keine Rolle und wuerde
 * nur zu Eintraegen fuehren, die nie treffen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_origins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->string('origin');
            $table->timestamps();

            $table->unique(['funnel_id', 'origin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_origins');
    }
};
