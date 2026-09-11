<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\PurchaseStatus;
use App\Constants\WalletOwnerType;
use App\Mail\Wallet\WalletLedgerMismatch;
use App\Models\LeadPurchase;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\SupportMailbox;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Taegliche Konsistenzpruefung des Wallet-Ledgers (LP-WALLET-015).
 *
 * Der Saldo eines Wallets ist die Summe seiner Buchungen; `balance_cents` und
 * `reserved_cents` sind nur fortgeschriebene Zwischenstaende, damit nicht jede
 * Anzeige das ganze Journal aufsummieren muss. Dieser Lauf ist der Nachweis,
 * dass beide Staende uebereinstimmen -- er laeuft ab Tag 1 mit und nicht erst,
 * wenn jemand eine Abweichung bemerkt hat.
 *
 * Zwei Pruefungen:
 *
 * 1. Je Wallet: Summe der Buchungen auf den freien Saldo gegen
 *    `balance_cents`, Summe der Buchungen auf den reservierten Betrag gegen
 *    `reserved_cents` (WalletTransactionType::affectsReservedBalance()).
 * 2. Ueber alle Kaeufer-Wallets: Summe der reservierten Betraege gegen die
 *    Summe der Kaufpreise aller Leadkaeufe im Stand `reserved`. Diese Klammer
 *    faengt den Fall, den die erste Pruefung nicht sieht: ein Ledger, das in
 *    sich stimmt, aber eine Reservierung traegt, zu der es keinen offenen Kauf
 *    mehr gibt (oder umgekehrt).
 *
 * Bei Abweichung: Protokolleintrag je Wallet mit Soll und Ist, eine Mail an die
 * Support-Adresse und Rueckgabewert 1 -- auch dann, wenn `--repair` die Staende
 * gerade korrigiert hat. Eine Abweichung bleibt ein Vorfall, den ein Mensch
 * ansehen muss; sie lautlos zu heilen, verdeckt ihre Ursache.
 *
 * `--repair` setzt die Zwischenstaende auf das Journal zurueck. Das geschieht
 * nur nach ausdruecklicher Freigabe: Ohne `--force` fragt der Befehl nach, und
 * im Zeitplan laeuft er ohne beides.
 */
class VerifyWalletLedger extends Command
{
    /**
     * Wallets je Durchgang. Gross genug, dass der Lauf wenige Abfragen braucht,
     * klein genug, dass die Aggregation nicht ueber den ganzen Bestand geht.
     */
    private const CHUNK_SIZE = 200;

    protected $signature = 'wallet:verify
        {--repair : Abgewichene Salden auf den Ledger-Stand zuruecksetzen}
        {--force : Rueckfrage vor der Korrektur ueberspringen}';

    protected $description = 'Prueft die fortgeschriebenen Wallet-Salden gegen das Ledger.';

    public function __construct(
        private readonly WalletService $wallets,
        private readonly SupportMailbox $support,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $repair = $this->confirmedRepair();

        if ($repair === null) {
            return self::FAILURE;
        }

        // Ticket #25: Die Meldung im Abweichungsfall ist der einzige Weg, auf
        // dem ein Mensch von einer Saldenabweichung erfaehrt. Steht dort keine
        // brauchbare Adresse, faellt sie aus -- das gehoert in jeden Lauf, nicht
        // erst in den mit Abweichung.
        if ($this->support->address() === null) {
            $this->warn('app.support_email ist nicht brauchbar gesetzt (leer, ungueltig oder Starterkit-Domain) -- eine Abweichung wuerde niemandem gemeldet.');
        }

        /** @var list<array{wallet_id: int, owner: string, balance_expected: int, balance_actual: int, reserved_expected: int, reserved_actual: int, repaired: bool}> $mismatches */
        $mismatches = [];
        $checked = 0;

        // chunkById statt get(): Der Bestand waechst mit jedem Mandanten, und
        // die Aggregation laeuft ohnehin je Durchgang in einer Abfrage.
        Wallet::query()->orderBy('id')->chunkById(self::CHUNK_SIZE, function (Collection $wallets) use (&$mismatches, &$checked, $repair): void {
            $checked += $wallets->count();

            $totals = WalletTransaction::query()->sumsByWallet(
                $wallets->map(fn (Wallet $wallet): int => (int) $wallet->getKey())->all(),
            );

            foreach ($wallets as $wallet) {
                $expected = $totals[$wallet->getKey()] ?? ['balance_cents' => 0, 'reserved_cents' => 0];

                if ($wallet->balance_cents === $expected['balance_cents']
                    && $wallet->reserved_cents === $expected['reserved_cents']) {
                    continue;
                }

                $mismatches[] = $this->reportMismatch($wallet, $expected, $repair);
            }
        });

        $reservationTotals = $this->checkReservationTotals();

        $this->renderSummary($mismatches, $reservationTotals, $checked);

        if ($mismatches === [] && $reservationTotals === null) {
            $this->info(sprintf('%d Wallet(s) geprueft, keine Abweichung.', $checked));

            return self::SUCCESS;
        }

        $this->notify($mismatches, $reservationTotals, $checked);

        return self::FAILURE;
    }

    /**
     * Soll repariert werden? Gibt null zurueck, wenn die Freigabe fehlt und der
     * Lauf deshalb abbrechen soll -- eine stillschweigend uebergangene
     * Korrektur waere schlimmer als ein Abbruch.
     */
    private function confirmedRepair(): ?bool
    {
        if (! $this->option('repair')) {
            return false;
        }

        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->error('--repair verlangt eine ausdrueckliche Freigabe: entweder interaktiv bestaetigen oder --force setzen.');

            return null;
        }

        if (! $this->confirm('Abgewichene Salden auf den Ledger-Stand zuruecksetzen?', false)) {
            $this->warn('Keine Freigabe -- es wird nur geprueft.');

            return false;
        }

        return true;
    }

    /**
     * Protokolliert eine Abweichung und korrigiert sie bei Freigabe.
     *
     * @param  array{balance_cents: int, reserved_cents: int}  $expected
     * @return array{wallet_id: int, owner: string, balance_expected: int, balance_actual: int, reserved_expected: int, reserved_actual: int, repaired: bool}
     */
    private function reportMismatch(Wallet $wallet, array $expected, bool $repair): array
    {
        $actual = [
            'balance_cents' => $wallet->balance_cents,
            'reserved_cents' => $wallet->reserved_cents,
        ];

        Log::error('Wallet-Saldo weicht vom Ledger ab.', [
            'wallet_id' => $wallet->getKey(),
            'owner_type' => $wallet->owner_type->value,
            'owner_id' => $wallet->owner_id,
            'soll' => $expected,
            'ist' => $actual,
        ]);

        $repaired = false;

        if ($repair) {
            // Der Service rechnet unter Sperre noch einmal nach; was er
            // tatsaechlich geschrieben hat, steht im Rueckgabewert.
            $repaired = $this->wallets->resyncFromLedger($wallet)['changed'];
        }

        return [
            'wallet_id' => (int) $wallet->getKey(),
            'owner' => $this->describeOwner($wallet),
            'balance_expected' => $expected['balance_cents'],
            'balance_actual' => $actual['balance_cents'],
            'reserved_expected' => $expected['reserved_cents'],
            'reserved_actual' => $actual['reserved_cents'],
            'repaired' => $repaired,
        ];
    }

    /**
     * Zweite Klammer: reservierte Betraege aller Kaeufer-Wallets gegen die
     * Kaufpreise der offenen Leadkaeufe.
     *
     * @return array{expected: int, actual: int}|null null, wenn beide Summen uebereinstimmen
     */
    private function checkReservationTotals(): ?array
    {
        $reservedInWallets = (int) Wallet::query()
            ->where('owner_type', WalletOwnerType::BUYER->value)
            ->sum('reserved_cents');

        $reservedInPurchases = (int) LeadPurchase::query()
            ->where('status', PurchaseStatus::RESERVED->value)
            ->sum('price_cents');

        if ($reservedInWallets === $reservedInPurchases) {
            return null;
        }

        Log::error('Reservierte Betraege der Kaeufer-Wallets passen nicht zu den offenen Leadkaeufen.', [
            'soll' => $reservedInPurchases,
            'ist' => $reservedInWallets,
            'differenz' => $reservedInWallets - $reservedInPurchases,
        ]);

        return ['expected' => $reservedInPurchases, 'actual' => $reservedInWallets];
    }

    /**
     * @param  list<array{wallet_id: int, owner: string, balance_expected: int, balance_actual: int, reserved_expected: int, reserved_actual: int, repaired: bool}>  $mismatches
     * @param  array{expected: int, actual: int}|null  $reservationTotals
     */
    private function renderSummary(array $mismatches, ?array $reservationTotals, int $checked): void
    {
        if ($mismatches !== []) {
            $this->table(
                ['Wallet', 'Besitzer', 'Saldo Soll', 'Saldo Ist', 'Reserviert Soll', 'Reserviert Ist', 'Korrigiert'],
                array_map(fn (array $row): array => [
                    '#'.$row['wallet_id'],
                    $row['owner'],
                    $this->formatMoney($row['balance_expected']),
                    $this->formatMoney($row['balance_actual']),
                    $this->formatMoney($row['reserved_expected']),
                    $this->formatMoney($row['reserved_actual']),
                    $row['repaired'] ? 'ja' : 'nein',
                ], $mismatches),
            );

            $this->error(sprintf('%d von %d Wallet(s) weichen vom Ledger ab.', count($mismatches), $checked));
        }

        if ($reservationTotals !== null) {
            $this->error(sprintf(
                'Reservierungen passen nicht zu den offenen Leadkaeufen: Wallets %s, Kaeufe %s (Differenz %s).',
                $this->formatMoney($reservationTotals['actual']),
                $this->formatMoney($reservationTotals['expected']),
                $this->formatMoney($reservationTotals['actual'] - $reservationTotals['expected']),
            ));
        }
    }

    /**
     * Meldung an den Betreiber. Eine Mail je Lauf, nicht je Abweichung -- bei
     * einem systematischen Fehler waere das Postfach sonst unbrauchbar.
     *
     * Eine fehlende oder unbrauchbare Support-Adresse darf die Pruefung nicht
     * scheitern lassen: Der Befund steht im Protokoll und im Rueckgabewert.
     *
     * @param  list<array{wallet_id: int, owner: string, balance_expected: int, balance_actual: int, reserved_expected: int, reserved_actual: int, repaired: bool}>  $mismatches
     * @param  array{expected: int, actual: int}|null  $reservationTotals
     */
    private function notify(array $mismatches, ?array $reservationTotals, int $checked): void
    {
        $recipient = $this->support->address();

        if ($recipient === null) {
            $this->warn('Keine Meldung verschickt: app.support_email ist nicht brauchbar gesetzt (leer, ungueltig oder Starterkit-Domain).');

            return;
        }

        Mail::to($recipient)->send(new WalletLedgerMismatch($mismatches, $reservationTotals, $checked));
    }

    private function describeOwner(Wallet $wallet): string
    {
        if (! $wallet->owner_type->belongsToTenant()) {
            return $wallet->owner_type->value;
        }

        return sprintf('%s #%s', $wallet->owner_type->value, $wallet->owner_id ?? '?');
    }

    private function formatMoney(int $cents): string
    {
        return sprintf('%s %s', number_format($cents / 100, 2, ',', '.'), (string) config('wallet.currency'));
    }
}
