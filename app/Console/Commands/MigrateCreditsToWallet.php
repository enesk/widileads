<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\WalletTransactionType;
use App\Models\Tenant;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * LP-WALLET-014: Ueberfuehrt die Salden des alten Guthabenjournals ins Wallet.
 *
 * Jeder Mandant mit einem Guthabensaldo in `credit_ledger` bekommt genau eine
 * Eroeffnungsbuchung im Kauf-Wallet: Guthaben mal
 * config('wallet.legacy_credit_value_cents') (Vorgabe 500, also 5,00 EUR je
 * Guthaben). Die alte Guthabenzahl bleibt in der Meta unter `legacy_credits`
 * stehen -- damit laesst sich jede Buchung auf ihre Herkunft zurueckfuehren,
 * auch nachdem die Tabelle verworfen ist.
 *
 * Der Lauf ist beliebig oft wiederholbar: Der Idempotenzschluessel
 * `opening_balance:tenant:{id}` liegt fest, ein zweiter Lauf bucht nichts
 * nach. Genau diesen Schluessel sucht die Drop-Migration, bevor sie
 * `credit_ledger` verwirft.
 *
 * Das Kommando muss vor `php artisan migrate` laufen, wenn eine Installation
 * noch Altsalden traegt: Die Drop-Migration bricht sonst ab -- absichtlich,
 * denn sie ist die einzige Stelle des Umbaus, die Geld vernichten koennte.
 */
class MigrateCreditsToWallet extends Command
{
    protected $signature = 'wallet:migrate-credits {--dry-run : Nur anzeigen, nichts buchen}';

    protected $description = 'Ueberfuehrt Salden aus credit_ledger als Eroeffnungsbuchung ins Wallet.';

    public function __construct(private readonly WalletService $wallets)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->ledgerExists()) {
            $this->info('Die Tabelle credit_ledger existiert nicht mehr. Es gibt nichts zu ueberfuehren.');

            return self::SUCCESS;
        }

        $valueCents = (int) config('wallet.legacy_credit_value_cents');

        if ($valueCents <= 0) {
            $this->error('config(\'wallet.legacy_credit_value_cents\') ist nicht gesetzt. Ohne Gegenwert je Guthaben wird nichts gebucht.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $failed = 0;

        foreach ($this->balances() as $tenantId => $credits) {
            $tenant = Tenant::query()->withoutGlobalScopes()->find($tenantId);

            if (! $tenant instanceof Tenant) {
                $rows[] = [$tenantId, $credits, '-', 'Mandant existiert nicht mehr'];
                $failed++;

                continue;
            }

            $key = 'opening_balance:tenant:'.$tenantId;

            if ($this->alreadyMigrated($key)) {
                $rows[] = [$tenantId, $credits, '-', 'bereits ueberfuehrt'];

                continue;
            }

            // Ein negativer Altsaldo laesst sich nicht als Eroeffnung buchen
            // (OPENING_BALANCE erhoeht immer) und ist ohnehin ein Fall fuer
            // einen Menschen: Er bedeutet, dass jemand mehr verbraucht hat,
            // als er hatte.
            if ($credits < 0) {
                $rows[] = [$tenantId, $credits, '-', 'negativer Saldo, von Hand klaeren'];
                $failed++;

                continue;
            }

            $amountCents = $credits * $valueCents;

            if ($dryRun) {
                $rows[] = [$tenantId, $credits, $this->euro($amountCents), 'wuerde gebucht'];

                continue;
            }

            try {
                $this->wallets->post(
                    Wallet::forBuyer($tenant),
                    WalletTransactionType::OPENING_BALANCE,
                    $amountCents,
                    'Eroeffnungssaldo aus dem alten Guthabenjournal',
                    meta: ['legacy_credits' => $credits, 'credit_value_cents' => $valueCents],
                    idempotencyKey: $key,
                );

                $rows[] = [$tenantId, $credits, $this->euro($amountCents), 'gebucht'];
            } catch (Throwable $exception) {
                $rows[] = [$tenantId, $credits, '-', 'Fehler: '.$exception->getMessage()];
                $failed++;
            }
        }

        if ($rows === []) {
            $this->info('Kein Mandant traegt einen Guthabensaldo. Es gibt nichts zu ueberfuehren.');

            return self::SUCCESS;
        }

        $this->table(['Mandant', 'Guthaben', 'Betrag', 'Ergebnis'], $rows);

        if ($dryRun) {
            $this->comment('Probelauf: Es wurde nichts gebucht. Ohne --dry-run erneut starten.');

            return self::SUCCESS;
        }

        if ($failed > 0) {
            $this->error($failed.' Mandant(en) konnten nicht ueberfuehrt werden. Die Drop-Migration bleibt so lange rot.');

            return self::FAILURE;
        }

        $this->info('Alle Salden sind ueberfuehrt. Jetzt kann php artisan migrate laufen.');

        return self::SUCCESS;
    }

    /**
     * Saldo je Mandant, Nullsalden fallen weg -- genau wie in der Pruefung der
     * Drop-Migration.
     *
     * @return array<int, int>
     */
    private function balances(): array
    {
        return DB::table('credit_ledger')
            ->select('tenant_id')
            ->selectRaw('SUM(credits) as saldo')
            ->groupBy('tenant_id')
            ->havingRaw('SUM(credits) <> 0')
            ->orderBy('tenant_id')
            ->pluck('saldo', 'tenant_id')
            ->map(static fn (mixed $saldo): int => (int) $saldo)
            ->all();
    }

    private function alreadyMigrated(string $key): bool
    {
        return DB::table('wallet_transactions')->where('idempotency_key', $key)->exists();
    }

    private function ledgerExists(): bool
    {
        return DB::getSchemaBuilder()->hasTable('credit_ledger');
    }

    private function euro(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' EUR';
    }
}
