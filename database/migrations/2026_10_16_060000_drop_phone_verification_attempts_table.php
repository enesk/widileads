<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket #97: Das Protokoll des abgeloesten SMS-Wegs faellt weg.
 *
 * Die Tabelle trug zwei Aufgaben: die Rate-Limits der Code-Anforderungen und
 * den Blick des Supports auf ein Konto. Beides haengt jetzt am
 * Bestaetigungsanruf -- die Grenzen am RateLimiter
 * (`funnel.call.caller_id.limits`, siehe App\Services\CallerIdService), der
 * Verlauf an `caller_ids` und am Audit-Log (`caller_id.requested` /
 * `caller_id.verified`).
 *
 * Die Zeilen werden nicht uebertragen: Ein Versuch aus dem SMS-Weg sagt nichts
 * ueber den Anruf-Weg, und was von einer alten Bestaetigung bleiben muss,
 * steht am Benutzer (`users.phone_number_verified_at` und
 * `phone_verification_channel`).
 *
 * `dropIfExists`, weil die Tabelle in Umgebungen fehlen kann, die nach #97
 * frisch aufgesetzt wurden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('phone_verification_attempts');
    }

    /**
     * Der Rueckweg legt die Tabelle leer wieder an -- die Zeilen sind weg.
     * Mehr ist nicht herzustellen, und ein `down`, das sich weigert, waere
     * beim Zurueckrollen eines Deployments ein Stolperstein.
     */
    public function down(): void
    {
        if (Schema::hasTable('phone_verification_attempts')) {
            return;
        }

        Schema::create('phone_verification_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone_e164', 32);
            $table->string('channel', 8);
            $table->string('verification_sid', 64)->nullable();
            $table->string('status', 16);
            $table->timestamp('cleared_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['phone_e164', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }
};
