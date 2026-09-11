<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-WALLET-003: Der Verkaeuferpreis.
 *
 * Es gibt im Funnel Builder keine eigene `sellers`-Tabelle -- Verkaeufer sind
 * Betreiber-Mandanten (App\Constants\TenantType::OPERATOR), an denen die Leads
 * haengen (leads.tenant_id). Die Ticketvorgabe "sellers bzw. users mit
 * Seller-Rolle" wird deshalb auf `tenants` umgesetzt.
 *
 * `lead_price_cents` ist der Preis, den der Verkaeufer fuer einen seiner Leads
 * verlangt, in Cent -- die Geldseite rechnet ausschliesslich in Cent, waehrend
 * der aeltere Funnelpreis (funnels.lead_price) noch ein Dezimalwert ist. Er
 * gilt als Vorgabe des Mandanten; der Funnel darf ihn weiterhin uebersteuern.
 *
 * `commission_percent` ist ein Override: null heisst, es gilt
 * config('wallet.commission_percent'). Damit laesst sich fuer einzelne
 * Verkaeufer ein abweichender Satz vereinbaren, ohne den Vorgabewert der
 * Plattform anzufassen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Der Spaltendefault ist ein reiner Datenbank-Rueckfall, damit
            // bestehende Zeilen und Einfuegungen an Eloquent vorbei nicht
            // ploetzlich zum Preis 0 verkaufen. Massgeblich fuer neue
            // Mandanten ist config('wallet.default_lead_price_cents'), gesetzt
            // in App\Observers\TenantObserver::creating() (LP-WALLET-017).
            $table->integer('lead_price_cents')
                ->default((int) config('wallet.default_lead_price_cents'))
                ->after('type');

            $table->decimal('commission_percent', 5, 2)->nullable()->after('lead_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['lead_price_cents', 'commission_percent']);
        });
    }
};
