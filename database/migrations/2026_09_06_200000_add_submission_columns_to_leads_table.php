<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FB-031: Der Lead bekommt seine fachlichen Daten.
 *
 * FB-030 hat bewusst nur Zustand und Abrechnungsgrundlage angelegt. Hier kommt
 * dazu, woraus ein Lead entstanden ist -- Funnel, Fassung, Sitzung, Punktzahl,
 * Ergebnis, Preis, Kontakt und Herkunft.
 *
 * Zwei Entscheidungen stecken in den Fremdschluesseln:
 *
 * - `funnel_id`, `funnel_version_id` und `public_session_id` loeschen den Lead
 *   nicht mit (`nullOnDelete`). Ein verkaufter Lead traegt eine Forderung; er
 *   darf nicht verschwinden, weil jemand seinen Funnel aufraeumt.
 * - `public_session_id` ist eindeutig. Damit kann dieselbe Sitzung auch bei
 *   gleichzeitigen Aufrufen keine zwei Leads erzeugen -- die Idempotenz haengt
 *   nicht allein an der Anwendung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('funnel_id')->nullable()->after('tenant_id')->constrained('funnels')->nullOnDelete();
            $table->foreignId('funnel_version_id')->nullable()->after('funnel_id')->constrained('funnel_versions')->nullOnDelete();
            $table->foreignId('public_session_id')->nullable()->unique()->after('funnel_version_id')->constrained('public_sessions')->nullOnDelete();

            $table->unsignedInteger('score')->default(0)->after('lead_state');

            // Der Ergebnisschluessel aus dem Snapshot ("4-7"), nicht die ID einer
            // Zeile aus funnel_results: die Live-Zeile darf sich aendern, die
            // veroeffentlichte Fassung nicht.
            $table->string('result_key', 50)->nullable()->after('score');

            // Preis zum Zeitpunkt der Entstehung -- aus funnels.lead_price, sonst
            // aus der Konfiguration. LeadStateService schreibt daraus spaeter
            // settled_price fest.
            $table->decimal('price_at_creation', 8, 2)->nullable()->after('result_key');

            // Kontakt in normalisierter Form, fuer Dublettenpruefung (FB-023),
            // DSGVO-Suche (FB-038) und Marktplatz-Filter.
            $table->string('phone_e164', 32)->nullable()->after('price_at_creation');
            $table->string('email_normalized')->nullable()->after('phone_e164');

            // Herkunft, uebernommen von der Sitzung (FB-022). Niemals die
            // Roh-IP, nur ihr gesalzener Hash.
            $table->string('utm_source')->nullable()->after('email_normalized');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_term')->nullable()->after('utm_campaign');
            $table->string('utm_content')->nullable()->after('utm_term');
            // text wie an der Sitzung (FB-022): Referrer-URLs sprengen 255 Zeichen.
            $table->text('referrer')->nullable()->after('utm_content');
            $table->string('embed_origin')->nullable()->after('referrer');
            $table->char('ip_hash', 64)->nullable()->after('embed_origin');
            $table->string('user_agent')->nullable()->after('ip_hash');

            $table->index(['funnel_id', 'lead_state', 'created_at']);
            $table->index('email_normalized');
            $table->index('phone_e164');
        });

        Schema::create('lead_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('field_key', 64);

            // JSON, weil eine Mehrfachauswahl eine Liste ist und eine Zahl eine
            // Zahl bleiben soll. Null bedeutet: anonymisiert (FB-037).
            $table->json('value')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['lead_id', 'field_key']);
            $table->index('field_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_answers');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['funnel_id']);
            $table->dropForeign(['funnel_version_id']);
            $table->dropForeign(['public_session_id']);

            $table->dropIndex(['funnel_id', 'lead_state', 'created_at']);
            $table->dropIndex(['email_normalized']);
            $table->dropIndex(['phone_e164']);

            $table->dropColumn([
                'funnel_id',
                'funnel_version_id',
                'public_session_id',
                'score',
                'result_key',
                'price_at_creation',
                'phone_e164',
                'email_normalized',
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
