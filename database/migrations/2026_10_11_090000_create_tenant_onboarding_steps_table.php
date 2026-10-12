<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-ONBOARD-002: Die abgehakten Onboarding-Schritte eines Workspaces.
 *
 * Eine Zeile je erledigtem Schritt -- kein Statusfeld am Workspace, das jemand
 * ueberschreiben koennte. Daraus folgt die Idempotenz: Der Unique-Index
 * (tenant_id, step) ist die eigentliche Zusicherung des Tickets, dass ein
 * zweites complete() nichts aendert, und zwar auch dann, wenn zwei Anfragen
 * gleichzeitig laufen. Der OnboardingService verlaesst sich darauf und faengt
 * den Doppeleintrag ab, statt vorher zu fragen.
 *
 * Die Tabelle heisst `tenant_onboarding_steps` und nicht
 * `workspace_onboarding_steps`: "Workspace" ist im Funnel Builder das Wort der
 * Oberflaeche fuer einen Mandanten, in der Datenbank heisst er ueberall
 * `tenant` (tenants, buyer_tenant_id, seller_tenant_id). Ein zweiter Name fuer
 * denselben Fremdschluessel waere die teurere Entscheidung.
 *
 * `completed_at` ist bewusst nicht nullable, obwohl das Ticket es so nennt:
 * Eine Zeile entsteht nur, wenn der Schritt erledigt ist. Ein offener Schritt
 * ist die fehlende Zeile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_onboarding_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Erlaubte Werte: App\Constants\OnboardingStep. Als String, wie
            // ueberall im Funnel Builder.
            $table->string('step', 40);

            $table->timestamp('completed_at');

            // Wer den Schritt erledigt hat. Null beim Backfill und bei
            // Schritten, die ein Listener automatisch abhakt (erster Anruf),
            // sowie nachdem das Konto geloescht wurde -- der Haken soll das
            // ueberleben.
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Was beim Abhaken feststand: gewaehlte Branchen, Umkreis,
            // Zahlungsweise. Fuer den Admin-Trichter (LP-ONBOARD-015) und weil
            // sich eine Entscheidung spaeter nicht mehr rekonstruieren laesst.
            $table->json('meta')->nullable();

            $table->timestamps();

            // Der Schritt gilt genau einmal je Workspace.
            $table->unique(['tenant_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_onboarding_steps');
    }
};
