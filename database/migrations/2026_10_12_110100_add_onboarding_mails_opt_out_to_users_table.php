<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-ONBOARD-014: Abmeldung von der Onboarding-Strecke.
 *
 * Nur von dieser einen Strecke. Transaktionsmails -- Kaufbestaetigung,
 * Aufladung, Fristende -- haengen nicht an diesem Schalter und duerfen es
 * auch nie: Sie sind Beleg und nicht Werbung.
 *
 * Die Spalte steht am Benutzer und nicht am Workspace, weil die Mails an
 * einen Menschen gehen und der Abmeldelink in seiner Mail steht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('onboarding_mails_opt_out')->default(false)->after('phone_verification_channel');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_mails_opt_out');
        });
    }
};
