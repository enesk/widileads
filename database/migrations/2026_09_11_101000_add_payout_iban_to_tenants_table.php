<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LP-WALLET-010: Die Bankverbindung des Verkaeufers.
 *
 * Es gibt im Funnel Builder keine `sellers`-Tabelle -- Verkaeufer sind
 * Betreiber-Mandanten (App\Constants\TenantType::OPERATOR), an denen die Leads
 * haengen. Die Ticketvorgabe "add_iban_to_sellers_table" wird deshalb auf
 * `tenants` umgesetzt, genau wie schon der Verkaeuferpreis (LP-WALLET-003).
 *
 * Gespeichert wird die vollstaendige IBAN, aber verschluesselt (Cast
 * `encrypted` auf App\Models\Tenant): Ausgezahlt werden kann nur, wer die
 * ganze Nummer hat, angezeigt wird im Portal und in der Auszahlungsliste
 * ausschliesslich die letzte Vierergruppe. Die Spalte ist deshalb `text` und
 * nicht `string(34)` -- der Geheimtext von Laravels Verschluesselung ist
 * deutlich laenger als die Klartext-IBAN und passt in kein varchar(34).
 *
 * Aus demselben Grund ist die Spalte nicht durchsuchbar und traegt keinen
 * Index: Ein Index auf einem Geheimtext waere nutzlos, weil zwei
 * Verschluesselungen derselben IBAN verschiedene Werte ergeben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('payout_iban')->nullable()->after('commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('payout_iban');
        });
    }
};
