<?php

declare(strict_types=1);

use App\Constants\PurchaseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LP-WALLET-003: Der Kaufbeleg wird zum Abrechnungsbeleg.
 *
 * `lead_purchases` gibt es seit FB-054; die Tabelle wird erweitert statt neu
 * angelegt, damit vorhandene Kaeufe und alles, was daran haengt (Sichtbarkeit
 * der Kontaktdaten, Reklamationen, Rueckmeldungen), erhalten bleiben.
 *
 * Neu ist die Geldseite: Wer verkauft hat, wie sich der Preis in Provision und
 * Verkaeufererloes teilt und wo das Geld gerade steht (PurchaseStatus). Die
 * drei Cent-Betraege werden zum Kaufzeitpunkt festgeschrieben und danach nie
 * geaendert -- auch nicht, wenn sich der Provisionssatz spaeter aendert
 * (Architekturleitsatz 4). Aus demselben Grund steht `commission_percent` als
 * Snapshot in der Zeile und wird nicht aus der Config nachgeschlagen.
 *
 * Bewusste Abweichung von der Ticketvorgabe: KEIN Unique-Index auf `lead_id`
 * allein. Seit FB-055 kann ein Funnel im Modus `shared` denselben Lead an
 * mehrere Kaeufer verkaufen; der vorhandene Unique-Index
 * (lead_id, buyer_tenant_id) bleibt deshalb die richtige Klammer -- ein Kaeufer
 * kauft denselben Lead hoechstens einmal.
 *
 * Bestandsdaten: Kaeufe aus der Credit-Zeit sind abgeschlossen und liefen nie
 * ueber ein Wallet. Sie erhalten `status = captured` und Provisions- wie
 * Erloesbetrag 0 -- eine nachtraeglich errechnete Provision waere eine
 * erfundene Buchung ohne Beleg im Ledger. Die Ueberfuehrung alter Guthaben ist
 * Sache von LP-WALLET-014.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table) {
            // Der verkaufende Betreiber-Mandant. Zunaechst nullable, weil
            // Bestandszeilen ihn noch nicht tragen; unten nachgetragen und
            // danach auf NOT NULL gezogen.
            $table->foreignId('seller_tenant_id')->nullable()->after('buyer_tenant_id')
                ->constrained('tenants')->cascadeOnDelete();

            // Provisionssatz in Prozent, festgeschrieben zum Kaufzeitpunkt.
            $table->decimal('commission_percent', 5, 2)->default(0)->after('price_cents');

            // Aufteilung des Kaufpreises. Es gilt immer:
            // price_cents = commission_cents + seller_net_cents.
            $table->integer('commission_cents')->default(0)->after('commission_percent');
            $table->integer('seller_net_cents')->default(0)->after('commission_cents');

            // Erlaubte Werte: App\Constants\PurchaseStatus. Gesetzt wird die
            // Spalte ausschliesslich vom PurchaseService (LP-WALLET-006).
            $table->string('status', 20)->default(PurchaseStatus::RESERVED->value)->after('seller_net_cents');

            $table->timestamp('reserved_at')->nullable()->after('status');
            $table->timestamp('captured_at')->nullable()->after('reserved_at');
            $table->timestamp('released_at')->nullable()->after('captured_at');
            $table->timestamp('refunded_at')->nullable()->after('released_at');

            // Traegt die Portalansichten: offene Reservierungen des Kaeufers
            // (LP-WALLET-011) und die Einnahmen des Verkaeufers (LP-WALLET-012).
            $table->index(['buyer_tenant_id', 'status']);
            $table->index(['seller_tenant_id', 'status']);
        });

        // Bestandszeilen nachziehen: Verkaeufer ist der Mandant, dem der Lead
        // gehoert; die Reservierung fiel mit dem Kauf zusammen.
        DB::table('lead_purchases')
            ->join('leads', 'leads.id', '=', 'lead_purchases.lead_id')
            ->update([
                'lead_purchases.seller_tenant_id' => DB::raw('leads.tenant_id'),
                'lead_purchases.status' => PurchaseStatus::CAPTURED->value,
                'lead_purchases.reserved_at' => DB::raw('lead_purchases.purchased_at'),
                'lead_purchases.captured_at' => DB::raw('lead_purchases.purchased_at'),
            ]);

        // Ein Kauf ohne Verkaeufer ist fachlich nicht denkbar.
        DB::statement('ALTER TABLE lead_purchases MODIFY seller_tenant_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE lead_purchases MODIFY reserved_at TIMESTAMP NOT NULL');
    }

    public function down(): void
    {
        // Reihenfolge: Erst der Fremdschluessel, dann die Indizes. InnoDB nutzt
        // den zusammengesetzten Index (seller_tenant_id, status) fuer den
        // Fremdschluessel und laesst ihn nicht fallen, solange der noch steht.
        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->dropForeign(['seller_tenant_id']);
        });

        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->dropIndex(['buyer_tenant_id', 'status']);
            $table->dropIndex(['seller_tenant_id', 'status']);
            $table->dropColumn([
                'seller_tenant_id',
                'commission_percent',
                'commission_cents',
                'seller_net_cents',
                'status',
                'reserved_at',
                'captured_at',
                'released_at',
                'refunded_at',
            ]);
        });
    }
};
