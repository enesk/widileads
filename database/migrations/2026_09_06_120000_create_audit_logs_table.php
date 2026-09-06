<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-005: Audit-Log fuer sicherheitsrelevante Vorgaenge.
 *
 * Eintraege sind unveraenderlich, deshalb gibt es nur created_at. Die IP-Adresse
 * wird ausschliesslich als gesalzener SHA-256-Hash gespeichert (64 Zeichen hex).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
