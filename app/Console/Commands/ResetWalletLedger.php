<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\PaymentMode;
use App\Constants\PurchaseStatus;
use App\Models\LeadPurchase;
use App\Models\PayoutRequest;
use App\Models\PostpaidApplication;
use App\Models\Settlement;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Setzt die gesamte Geldseite des Marktplatzes auf null zurueck.
 *
 * Gedacht fuer Entwicklung und Abnahme: Nach einem Testdurchlauf mit
 * Aufladungen, Leadkaeufen, Einzuegen und Erstattungen soll wieder derselbe
 * Stand herrschen wie vor dem ersten Euro -- ohne `migrate:fresh`, das auch
 * Funnels, Leads und Mandanten mitnimmt.
 *
 * Der Lauf leert das Journal (`wallet_transactions`) und alles, was sich
 * daraus ableitet -- Abrechnungen und Auszahlungsanforderungen --, und setzt
 * die fortgeschriebenen Salden aller Wallets auf 0. Die Reihenfolge ist
 * wichtig: Erst wenn das Journal leer ist, ist ein Saldo von 0 auch der
 * Wahrheit entsprechend; `wallet:verify` prueft genau diese Klammer.
 *
 * Zwei Dinge bleiben bewusst stehen:
 *
 * - Zahlungsmittel (`payment_methods`). Ein hinterlegtes SEPA-Mandat ist keine
 *   Buchung, sondern eine Erlaubnis bei Stripe. Es hier zu loeschen, wuerde den
 *   Kaeufer zwingen, es fuer den naechsten Testlauf neu zu erteilen.
 * - Der Zahlungsmodus samt Kreditrahmen. Wer auch Pay as you go wieder auf den
 *   Ausgangsstand bringen will, nimmt `--postpaid` dazu; dann fallen auch die
 *   Antraege weg.
 *
 * Leadkaeufe werden nicht geloescht -- die Belege samt Anrufversuchen und
 * Reklamationen bleiben erhalten. Kaeufe im Stand `reserved` werden aber auf
 * `released` gesetzt: Eine offene Reservierung ohne Deckung im Journal waere
 * genau die Abweichung, die `wallet:verify` in seiner zweiten Klammer meldet.
 *
 * Zurueckholen laesst sich davon nichts. Der Lauf fragt deshalb nach und
 * bricht in einer Produktionsumgebung ab -- auch mit `--force`, wie die
 * gesperrten Migrationsbefehle (App\Console\DestructiveCommandGuard, FB-003).
 * Echtes Geld raeumt niemand per Befehl ab.
 */
class ResetWalletLedger extends Command
{
    protected $signature = 'wallet:reset
        {--postpaid : Zahlungsmodus, Kreditrahmen und Antraege ebenfalls zuruecksetzen}
        {--dry-run : Nur anzeigen, was geloescht wuerde}
        {--force : Rueckfrage ueberspringen}';

    protected $description = 'Loescht alle Wallet-Buchungen und setzt alle Salden auf 0.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $withPostpaid = (bool) $this->option('postpaid');

        $counts = $this->counts($withPostpaid);

        $this->table(['Was', 'Anzahl'], collect($counts)->map(
            fn (int $count, string $label): array => [$label, $count],
        )->values()->all());

        if ($dryRun) {
            $this->comment('Probelauf: Es wurde nichts geaendert. Ohne --dry-run erneut starten.');

            return self::SUCCESS;
        }

        if (app()->environment('production')) {
            $this->error('wallet:reset ist in der Produktionsumgebung gesperrt -- auch mit --force.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Alle Buchungen loeschen und alle Salden auf 0 setzen? Das laesst sich nicht rueckgaengig machen.', false)) {
            $this->comment('Abgebrochen. Es wurde nichts geaendert.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($withPostpaid): void {
            // Abrechnungen und Auszahlungen zuerst: Sie sind Forderungen, die
            // sich aus dem Journal ergeben, und haetten ohne es keinen Bezug
            // mehr.
            Settlement::query()->delete();
            PayoutRequest::query()->delete();

            // Bewusst am Model vorbei ueber den DB-Builder: Der
            // WalletTransactionQueryBuilder verbietet jedes Loeschen im
            // Journal (LP-WALLET-004), und das soll er auch weiterhin -- eine
            // einzelne Buchung wird nie geloescht, sondern gegengebucht.
            // Diese eine Stelle raeumt das Journal als Ganzes ab und ist
            // deshalb die bewusste Ausnahme, nicht die Aufweichung der Regel.
            // delete() und kein truncate(): truncate committet in MySQL
            // implizit und wuerde die Klammer dieser Transaktion sprengen.
            DB::table('wallet_transactions')->delete();

            LeadPurchase::query()
                ->where('status', PurchaseStatus::RESERVED->value)
                ->update([
                    'status' => PurchaseStatus::RELEASED->value,
                    'released_at' => now(),
                ]);

            $reset = [
                'balance_cents' => 0,
                'reserved_cents' => 0,
                // Die Sperre haengt am negativen Saldo. Ein Saldo von 0 mit
                // gesperrtem Kaeufer waere ein Zustand, aus dem er nicht mehr
                // herauskaeme.
                'purchase_blocked' => false,
            ];

            if ($withPostpaid) {
                $reset += [
                    'payment_mode' => PaymentMode::PREPAID->value,
                    'credit_limit_cents' => 0,
                    'postpaid_enabled_at' => null,
                    'postpaid_enabled_by' => null,
                    'postpaid_disabled_at' => null,
                    'postpaid_disabled_reason' => null,
                ];

                PostpaidApplication::query()->delete();
            }

            Wallet::query()->update($reset);
        });

        $this->info('Alle Buchungen sind geloescht, alle Salden stehen auf 0.');
        $this->comment('Gegenprobe: php artisan wallet:verify');

        return self::SUCCESS;
    }

    /**
     * Was der Lauf anfassen wird, vor der Rueckfrage.
     *
     * @return array<string, int>
     */
    private function counts(bool $withPostpaid): array
    {
        $counts = [
            'Buchungen (wallet_transactions)' => WalletTransaction::query()->count(),
            'Abrechnungen (settlements)' => Settlement::query()->count(),
            'Auszahlungsanforderungen (payout_requests)' => PayoutRequest::query()->count(),
            'Wallets auf 0 zu setzen' => Wallet::query()->count(),
            'Leadkaeufe reserved -> released' => LeadPurchase::query()
                ->where('status', PurchaseStatus::RESERVED->value)
                ->count(),
        ];

        if ($withPostpaid) {
            $counts['Postpaid-Antraege (postpaid_applications)'] = PostpaidApplication::query()->count();
        }

        return $counts;
    }
}
