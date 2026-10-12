<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LP-WALLET-013: Ein Lead mit Kaufbeleg darf nicht geloescht werden.
 *
 * Bisher hing `lead_purchases.lead_id` mit `cascadeOnDelete` am Lead
 * (Migration 2026_09_07_154500). Jedes Loeschen eines Leads nahm damit seinen
 * Kaufbeleg mit -- auch einen im Stand `reserved`. Das Journal
 * `wallet_transactions` ist append-only (LP-WALLET-004) und behaelt die
 * `reserve`-Zeile, also blockte `wallets.reserved_cents` danach Geld, zu dem
 * es keinen Beleg mehr gab (Ticket #78: 16,13 EUR auf Wallet 2).
 *
 * Deshalb `restrictOnDelete`: Der Kaufbeleg ist der Beleg der Geldseite, die
 * Datenbank gibt ihn nicht mehr von selbst her. Wer einen Lead wirklich
 * loeschen will, loest eine offene Reservierung vorher ueber
 * PurchaseService::release() auf; abgebuchte Kaeufe bleiben als Beleg stehen
 * und ein verkaufter Lead bleibt damit dauerhaft unloeschbar. Fuer die
 * Aufbewahrungsfrist ist das kein Verlust: Der LeadRetentionService
 * anonymisiert, er loescht nicht.
 *
 * Alle uebrigen Kaskaden bleiben, wie sie sind: An `lead_state_log`,
 * `lead_answers`, `lead_watchlist`, `call_attempts` und `lead_complaints`
 * haengt kein Geld, dort ist das Mitloeschen richtig.
 *
 * Drei Dinge, die dieser Lauf beruecksichtigt:
 *
 * 1. Der Fremdschluessel ist nicht in jeder Datenbank vorhanden. Auf dem
 *    Live-Stand und lokal fehlt er -- er wurde beim Aufraeumen per SQL
 *    entfernt, das Ticket #78 ausgeloest hat. Gedroppt wird deshalb nur, was
 *    wirklich da ist.
 * 2. `restrict` laesst sich nur anlegen, wenn kein Kaufbeleg auf einen
 *    fehlenden Lead zeigt. Verwaiste Belege bricht der Lauf deshalb VOR dem
 *    ersten DDL ab -- dann bleibt die Datenbank unveraendert und der zweite
 *    Versuch kostet nichts.
 * 3. `lead_id` traegt keinen eigenen Index, InnoDB nutzt fuer den
 *    Fremdschluessel das fuehrende Feld des `unique(lead_id, buyer_tenant_id)`.
 *    Der bleibt unberuehrt, deshalb braucht es hier keine Index-Akrobatik.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->guardAgainstOrphanedPurchases();
        $this->dropLeadForeignKeyIfPresent();

        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->foreign('lead_id')->references('id')->on('leads')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropLeadForeignKeyIfPresent();

        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });
    }

    /**
     * Bricht ab, solange Kaufbelege auf geloeschte Leads zeigen.
     *
     * Die Meldung nennt die Belege, damit sie ueber den Domaenenweg aufgeloest
     * werden koennen: Eine offene Reservierung gehoert durch
     * PurchaseService::release(), ein entschiedener Beleg ohne Lead ist
     * geldneutral und kann von Hand entfernt werden.
     */
    private function guardAgainstOrphanedPurchases(): void
    {
        /** @var list<object{id: int, lead_id: int, status: string}> $orphans */
        $orphans = DB::table('lead_purchases')
            ->leftJoin('leads', 'leads.id', '=', 'lead_purchases.lead_id')
            ->whereNull('leads.id')
            ->orderBy('lead_purchases.id')
            ->limit(20)
            ->get(['lead_purchases.id', 'lead_purchases.lead_id', 'lead_purchases.status'])
            ->all();

        if ($orphans === []) {
            return;
        }

        $list = implode(', ', array_map(
            static fn (object $row): string => "#{$row->id} (Lead {$row->lead_id}, {$row->status})",
            $orphans,
        ));

        throw new RuntimeException(
            'lead_purchases.lead_id kann nicht auf restrict umgestellt werden: '
            ."Es gibt Kaufbelege ohne Lead: {$list}. "
            .'Reservierte Belege zuerst ueber PurchaseService::release() aufloesen, '
            .'entschiedene Belege ohne Lead entfernen, danach die Migration erneut starten.'
        );
    }

    private function dropLeadForeignKeyIfPresent(): void
    {
        $existing = collect(Schema::getForeignKeys('lead_purchases'))
            ->first(fn (array $key): bool => $key['columns'] === ['lead_id']);

        if ($existing === null) {
            return;
        }

        Schema::table('lead_purchases', function (Blueprint $table) use ($existing) {
            $table->dropForeign($existing['name']);
        });
    }
};
