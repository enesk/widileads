<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-015: Millisekunden fuer die Zeitstempel, gegen die der Builder seinen
 * Konfliktschutz prueft.
 *
 * Der Builder speichert automatisch und vergleicht dabei updated_at mit dem
 * Stand, auf dem der Bearbeiter aufgesetzt hat. Ein Zeitstempel mit
 * Sekundengenauigkeit macht zwei Speichervorgaenge innerhalb derselben Sekunde
 * ununterscheidbar - also genau dort blind, wo Kollisionen bei Autosave am
 * wahrscheinlichsten sind.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = ['funnel_steps', 'funnel_questions'];

    public function up(): void
    {
        $this->setPrecision(3);
    }

    public function down(): void
    {
        $this->setPrecision(0);
    }

    private function setPrecision(int $precision): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($precision): void {
                $blueprint->timestamp('created_at', $precision)->nullable()->change();
                $blueprint->timestamp('updated_at', $precision)->nullable()->change();
            });
        }
    }
};
