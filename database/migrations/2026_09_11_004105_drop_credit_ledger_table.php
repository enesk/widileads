<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LP-WALLET-018: Das alte Guthabenjournal aus FB-052 faellt.
 *
 * Seit dem Cutover fuehrt App\Services\Wallet\WalletService das einzige
 * Journal, das Geld bewegt; die alten Salden sind mit `wallet:migrate-credits`
 * als Eroeffnungsbuchungen ins Wallet ueberfuehrt. Was hier stand, war seitdem
 * nur noch Nachweis -- und ein Nachweis, den niemand mehr liest, ist eine
 * zweite Wahrheit ueber Geld, die irgendwann jemand zu glauben anfaengt.
 *
 * Der Beleg geht damit nicht verloren: Die Eroeffnungsbuchungen im
 * Wallet-Ledger tragen die alte Guthabenzahl in ihrer Meta (`legacy_credits`),
 * und vor dem Drop wurde ein Dump der Tabelle gezogen und abgelegt
 * (storage/app/backups/credit_ledger_<datum>.sql). Beides zusammen
 * rekonstruiert jede Zeile, die hier gestanden hat.
 *
 * Das `down()` legt die Tabelle in ihrem letzten Stand wieder an -- also
 * einschliesslich der Waehrungsspalte aus FB-052a, denn ein Rollback dieser
 * Migration fuehrt auf genau den Stand zurueck, der vor ihr galt. Sie kommt
 * leer zurueck: Zeilen wiederherzustellen ist Sache des Dumps, nicht einer
 * Migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->guardAgainstUnmigratedBalances();

        Schema::dropIfExists('credit_ledger');
    }

    /**
     * Abbrechen, solange ein Altsaldo noch nicht im Wallet steht.
     *
     * Der Drop ist die einzige Stelle dieses Umbaus, die Geld vernichten kann:
     * Wurde die Ueberfuehrung aus LP-WALLET-014 auf einer Installation nie
     * gefahren, waere der Kontostand ihrer Kaeufer danach weg und aus dem
     * Wallet nicht rekonstruierbar. Die Reihenfolge wird deshalb hier
     * erzwungen und nicht einer Betriebsanweisung ueberlassen.
     *
     * Geprueft wird pro Mandant, nicht in der Summe: Ein einziger vergessener
     * Kaeufer faellt in einer Gesamtsumme nicht auf.
     */
    private function guardAgainstUnmigratedBalances(): void
    {
        if (! Schema::hasTable('credit_ledger') || ! Schema::hasTable('wallet_transactions')) {
            return;
        }

        $missing = DB::table('credit_ledger')
            ->select('tenant_id')
            ->selectRaw('SUM(credits) as saldo')
            ->groupBy('tenant_id')
            ->havingRaw('SUM(credits) <> 0')
            ->get()
            ->reject(static fn (object $row): bool => DB::table('wallet_transactions')
                ->where('idempotency_key', 'opening_balance:tenant:'.$row->tenant_id)
                ->exists());

        if ($missing->isEmpty()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Abbruch: %d Mandant(en) haben einen Guthabensaldo ohne Eroeffnungsbuchung im Wallet (%s). '
            .'Zuerst die Ueberfuehrung aus LP-WALLET-014 fahren (wallet:migrate-credits, Stand vor diesem Commit), '
            .'dann diese Migration erneut starten.',
            $missing->count(),
            $missing->map(static fn (object $row): string => 'Mandant '.$row->tenant_id.': '.$row->saldo)->implode(', '),
        ));
    }

    public function down(): void
    {
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 20);
            $table->integer('credits');
            $table->unsignedBigInteger('amount_cents')->nullable();

            // Aus FB-052a: gehoert zur ganzen Buchung, nicht nur zum Betrag.
            $table->char('currency', 3);

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
            $table->unique(['type', 'reference_type', 'reference_id'], 'credit_ledger_reference_unique');
        });
    }
};
