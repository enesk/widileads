<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-022: Herkunft einer oeffentlichen Sitzung.
 *
 * Die Herkunft haengt an der Sitzung, nicht am spaeteren Lead: Sie entsteht beim
 * ersten Aufruf, also lange bevor klar ist, ob ueberhaupt eine Anfrage daraus
 * wird. FB-031 uebernimmt sie von hier an den Lead.
 *
 * Gespeichert wird ausschliesslich der gesalzene SHA-256-Hash der IP-Adresse,
 * nie die Adresse selbst -- Architekturleitsatz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_sessions', function (Blueprint $table) {
            $table->string('utm_source')->nullable()->after('current_step');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_term')->nullable()->after('utm_campaign');
            $table->string('utm_content')->nullable()->after('utm_term');
            $table->text('referrer')->nullable()->after('utm_content');
            // Origin der einbettenden Seite (iFrame-Embed, FB-024).
            $table->string('embed_origin')->nullable()->after('referrer');
            $table->char('ip_hash', 64)->nullable()->after('embed_origin');
            $table->string('user_agent')->nullable()->after('ip_hash');

            $table->index('ip_hash');
            $table->index(['utm_source', 'utm_campaign']);
        });
    }

    public function down(): void
    {
        Schema::table('public_sessions', function (Blueprint $table) {
            $table->dropIndex(['ip_hash']);
            $table->dropIndex(['utm_source', 'utm_campaign']);

            $table->dropColumn([
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'referrer',
                'embed_origin',
                'ip_hash',
                'user_agent',
            ]);
        });
    }
};
