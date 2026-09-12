<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\PaymentMode;
use App\Constants\SettlementStatus;
use App\Constants\WalletOwnerType;
use App\Models\Settlement;
use App\Models\Wallet;
use App\Services\Wallet\SettlementPrenotifier;
use App\Services\Wallet\SettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Der Einzug offener Postpaid-Betraege (LP-POSTPAID-008).
 *
 * Zwei Betriebsarten in einem Kommando, weil beide denselben zweiten Schritt
 * teilen:
 *
 * - Ohne Option der woechentliche Termin: Fuer jedes Postpaid-Wallet mit
 *   negativem Saldo entsteht eine Forderung, danach wird belastet.
 * - Mit `--retries` der stuendliche Nachlauf: Nur Forderungen, deren zweiter
 *   Versuch faellig ist. Stuendlich, weil die Wiederholungsfristen ueber den
 *   Tag verteilt ablaufen.
 *
 * Dazwischen liegt die SEPA-Vorabankuendigung (LP-POSTPAID-014): Eine
 * Lastschrift darf erst nach Ablauf der angekuendigten Frist belastet werden,
 * eine Karte sofort. Deshalb ist der Lauf, der eine Forderung anlegt, bei SEPA
 * nicht derselbe, der sie einzieht -- die Forderung bleibt bis zum
 * Belastungsdatum liegen und wird von einem spaeteren Lauf aufgegriffen.
 *
 * Belastet wird ueber den ChargeSettlementJob und nicht im Kommando: Ein
 * haengender Stripe-Aufruf soll nicht den ganzen Lauf blockieren.
 */
class SettleWallets extends Command
{
    protected $signature = 'wallet:settle
        {--charge-only : Keine neuen Forderungen anlegen, nur faellige belasten}
        {--dry-run : Nur anzeigen, was geschehen wuerde}';

    protected $description = 'Zieht offene Postpaid-Betraege ein (LP-POSTPAID-008).';

    public function handle(SettlementService $settlements, SettlementPrenotifier $prenotifier): int
    {
        if (! (bool) config('wallet.postpaid.enabled')) {
            $this->info('Pay as you go ist abgeschaltet (wallet.postpaid.enabled); es wird nichts eingezogen.');

            return self::SUCCESS;
        }

        $chargeOnly = (bool) $this->option('charge-only');
        $dryRun = (bool) $this->option('dry-run');

        $created = $chargeOnly ? 0 : $this->createDueSettlements($settlements, $dryRun);

        // Ankuendigen vor dem Belasten: Ohne `prenotified_at` laesst
        // Settlement::mayBeCharged() die Lastschrift nicht zu, die Forderung
        // bliebe sonst dauerhaft liegen.
        $announced = $dryRun ? 0 : $prenotifier->announcePending();

        $charged = $this->chargeDueSettlements($settlements, $dryRun);

        $this->table(
            ['Neue Forderungen', 'Ankuendigungen', 'Einzuege angestossen'],
            [[$created, $announced, $charged]],
        );

        return self::SUCCESS;
    }

    /**
     * Legt fuer jedes Postpaid-Wallet mit offenem Betrag eine Forderung an.
     *
     * chunkById und nicht get(): Die Zahl der Kaeufer waechst, der
     * Arbeitsspeicher des Laufs soll es nicht. Die Entscheidung, ob wirklich
     * eine Forderung entsteht -- Saldo, laufender Einzug, Zahlungsmittel --,
     * faellt unter Sperre im SettlementService; diese Abfrage ist nur die
     * Vorauswahl.
     */
    private function createDueSettlements(SettlementService $settlements, bool $dryRun): int
    {
        $created = 0;

        Wallet::query()
            ->where('owner_type', WalletOwnerType::BUYER->value)
            ->where('payment_mode', PaymentMode::POSTPAID->value)
            ->where('balance_cents', '<', 0)
            ->chunkById(100, function ($wallets) use ($settlements, $dryRun, &$created): void {
                foreach ($wallets as $wallet) {
                    if ($dryRun) {
                        $this->line(sprintf(
                            'Wallet #%d: offener Betrag %d Cent.',
                            $wallet->getKey(),
                            $wallet->open_amount_cents,
                        ));
                        $created++;

                        continue;
                    }

                    if ($settlements->create($wallet, Settlement::TRIGGER_SCHEDULED) !== null) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    /**
     * Stellt alle belastbaren Forderungen in die Queue.
     *
     * Das sind drei Gruppen in einer Abfrage: Kartenforderungen (sofort
     * belastbar), Lastschriften, deren angekuendigtes Belastungsdatum erreicht
     * ist, und faellige Wiederholungen. Die zweite Gruppe ist der Grund, warum
     * der stuendliche Lauf nicht auf `retry_pending` eingeschraenkt ist: Eine
     * am Montag angekuendigte Lastschrift wird am Dienstag belastet und nicht
     * erst am naechsten Montag.
     */
    private function chargeDueSettlements(SettlementService $settlements, bool $dryRun): int
    {
        $now = Carbon::now();
        $charged = 0;

        $query = Settlement::query()
            ->chargeable($now)
            ->with('paymentMethod')
            ->where(function ($inner) use ($now): void {
                $inner->where('status', '!=', SettlementStatus::RETRY_PENDING->value)
                    ->orWhereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', $now);
            });

        $query->chunkById(100, function ($chunk) use ($settlements, $dryRun, &$charged): void {
            foreach ($chunk as $settlement) {
                if ($dryRun) {
                    $this->line(sprintf(
                        'Einzug #%d: %d Cent ueber Zahlungsmittel #%s.',
                        $settlement->getKey(),
                        $settlement->amount_cents,
                        $settlement->payment_method_id ?? '-',
                    ));
                } else {
                    $settlements->dispatchCharge($settlement);
                }

                $charged++;
            }
        });

        return $charged;
    }
}
