<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-010: Grundgeruest eines Funnels -- Funnel, Schritte, Fragen, Antwortoptionen,
 * Verzweigungsregeln und Ergebnis-Screens.
 *
 * Oeffentlich adressiert wird ein Funnel ausschliesslich ueber public_token
 * (ULID), nie ueber die ID. Der Feldschluessel einer Frage ist je Funnel
 * eindeutig, deshalb traegt funnel_questions zusaetzlich funnel_id -- ein
 * Unique-Index ueber die Schritt-Grenze hinweg ist sonst nicht moeglich.
 *
 * funnel_conditions und funnel_results legen nur die Struktur an; der
 * StepResolver (FB-012) und der ResultResolver (FB-013) kommen spaeter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->char('public_token', 26)->unique();
            $table->string('name');
            $table->string('slug');
            $table->string('status', 20)->default('draft');
            $table->decimal('lead_price', 8, 2)->nullable();
            $table->unsignedInteger('contact_step_position')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('funnel_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['funnel_id', 'position']);
        });

        Schema::create('funnel_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('funnel_steps')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 32);
            $table->string('field_key', 64);
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(true);
            $table->json('validation')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['funnel_id', 'field_key']);
            $table->index(['step_id', 'position']);
        });

        Schema::create('funnel_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('funnel_questions')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('label');
            $table->string('value');
            $table->integer('score')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['question_id', 'position']);
        });

        Schema::create('funnel_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->foreignId('source_question_id')->constrained('funnel_questions')->cascadeOnDelete();
            // Erlaubte Werte: App\Constants\ConditionOperator.
            $table->string('operator', 20);
            $table->json('value')->nullable();
            $table->foreignId('target_step_id')->constrained('funnel_steps')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['funnel_id', 'priority']);
        });

        Schema::create('funnel_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->integer('min_score');
            $table->integer('max_score');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->boolean('show_contact_form')->default(true);
            $table->timestamps();

            $table->index(['funnel_id', 'min_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_results');
        Schema::dropIfExists('funnel_conditions');
        Schema::dropIfExists('funnel_options');
        Schema::dropIfExists('funnel_questions');
        Schema::dropIfExists('funnel_steps');
        Schema::dropIfExists('funnels');
    }
};
