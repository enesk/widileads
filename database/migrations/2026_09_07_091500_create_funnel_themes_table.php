<?php

use App\Constants\FunnelProgressStyle;
use App\Constants\FunnelThemeFont;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-017: Erscheinungsbild eines Funnels.
 *
 * Genau ein Theme je Funnel, deshalb der Unique-Index auf funnel_id. Farben
 * stehen als Hex-Wert, Schrift und Fortschrittsanzeige als Enum-Wert - die
 * Whitelist steckt in App\Constants\FunnelThemeFont bzw. FunnelProgressStyle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('primary_color', 7)->default('#2563eb');
            $table->string('secondary_color', 7)->default('#64748b');
            $table->string('background_color', 7)->default('#ffffff');
            $table->string('text_color', 7)->default('#0f172a');

            $table->string('font')->default(FunnelThemeFont::SYSTEM->value);
            $table->string('logo_path')->nullable();
            $table->string('progress_style')->default(FunnelProgressStyle::BAR->value);

            $table->string('button_next_label')->nullable();
            $table->string('button_back_label')->nullable();
            $table->string('button_submit_label')->nullable();

            // Eckenradius in Pixel. Ganzzahlig, weil Bruchteile in der
            // Darstellung nichts bringen und nur Abstimmungsaufwand erzeugen.
            $table->unsignedSmallInteger('border_radius')->default(8);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_themes');
    }
};
