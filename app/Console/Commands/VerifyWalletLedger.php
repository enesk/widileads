<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\PaymentMode;
use App\Constants\PurchaseStatus;
use App\Constants\SettlementStatus;
use App\Constants\WalletOwnerType;
use App\Constants\WalletTransactionType;
use App\Mail\Wallet\WalletLedgerMismatch;
use App\Models\LeadPurchase;
use App\Models\Settlement;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\SupportMailbox;
use App\Services\Wallet\WalletService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
 * 3. Postpaid-Invarianten (LP-POSTPAID-013). Sie pruefen nicht den Saldo gegen
 *    das Journal, sondern die Regeln, die Pay as you go ueber das Journal
 *    hinaus setzt -- Kreditrahmen, Einzug und Aufschlag. Sie sind im Einzelnen
 *    an checkPostpaidInvariants() beschrieben und werden nie repariert: Eine
 *    verletzte Regel ist ein Fehler im Ablauf, kein abgelaufener
 *    Zwischenstand.
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

    /**
     * Belegbefunde je Wallet in Konsole und Mail. Darueber steht nur noch eine
     * Sammelzeile -- vollstaendig bleibt der Befund im Protokoll.
     */
    private const PURCHASE_FINDING_LIMIT = 20;

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
        $reservationFindings = $this->checkReservations($reservationTotals);
        $findings = [...$reservationFindings, ...$this->checkPostpaidInvariants()];

        $this->renderSummary($mismatches, $reservationTotals, $findings, $checked, $reservationFindings !== []);

        if ($mismatches === [] && $reservationTotals === null && $findings === []) {
            $this->info(sprintf('%d Wallet(s) geprueft, keine Abweichung.', $checked));

            return self::SUCCESS;
        }

        $this->notify($mismatches, $reservationTotals, $findings, $checked, $reservationFindings !== []);

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

        // Preis UND Aufschlag: Reserviert wird beim Postpaid-Kauf der
        // Gesamtbetrag (PurchaseService::buyerTotalCents), sonst meldete diese
        // Klammer ab dem ersten Pay-as-you-go-Kauf eine Abweichung, die keine
        // ist. Bei Prepaid ist der Aufschlag 0, die Rechnung bleibt die alte.
        $reservedInPurchases = (int) LeadPurchase::query()
            ->where('status', PurchaseStatus::RESERVED->value)
            ->sum(DB::raw('price_cents + surcharge_cents'));

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
     * Ebene 2 und 3 der zweiten Klammer: nicht nur dass eine Reservierung
     * fehlt, sondern wessen Guthaben sie blockiert, welcher Kaufbeleg dahinter
     * steht und seit wann (Ticket #81).
     *
     * Ebene 2 laeuft immer und braucht eine Abfrage: die Kaeufer-Wallets gegen
     * die nach Kaeufer gruppierten offenen Leadkaeufe. Ebene 3 laeuft nur fuer
     * die dabei auffaelligen Wallets -- im gruenen Bestand also gar nicht und
     * ohne zusaetzliche Abfrage.
     *
     * Die Restdifferenz (Gesamtdifferenz minus Summe der Wallet-Differenzen)
     * wird gerechnet, nicht abgefragt: Sie faengt offene Kaeufe, deren Kaeufer
     * gar kein Wallet hat, und waere sonst die einzige unerklaerte Zahl.
     *
     * @param  array{expected: int, actual: int}|null  $reservationTotals
     * @return list<array{check: string, subject: string, detail: string}>
     */
    private function checkReservations(?array $reservationTotals): array
    {
        $wallets = $this->walletsWithReservationGap();

        if ($wallets === [] && $reservationTotals === null) {
            return [];
        }

        $byWallet = $wallets === []
            ? []
            : $this->purchaseReservationFindings(array_map(static fn (array $row): int => $row['wallet_id'], $wallets));

        $findings = [];

        foreach ($wallets as $row) {
            $findings[] = $this->logged([
                'check' => __('marketplace.wallet.verify.findings.wallet_reservation.check'),
                'subject' => sprintf('Wallet #%d (%s)', $row['wallet_id'], $row['owner']),
                'detail' => __('marketplace.wallet.verify.findings.wallet_reservation.detail', [
                    'actual' => $this->formatMoney($row['actual']),
                    'count' => (string) $row['count'],
                    'expected' => $this->formatMoney($row['expected']),
                    'difference' => $this->formatMoney($row['actual'] - $row['expected']),
                ]),
            ]);

            // Die Belegbefunde stehen direkt unter ihrem Wallet: Wer die Mail
            // liest, soll den Weg von grob nach fein nicht selbst sortieren.
            $findings = [...$findings, ...($byWallet[$row['wallet_id']] ?? [])];
        }

        $difference = $reservationTotals === null ? 0 : $reservationTotals['actual'] - $reservationTotals['expected'];
        $assigned = array_sum(array_map(static fn (array $row): int => $row['actual'] - $row['expected'], $wallets));

        if ($difference - $assigned !== 0) {
            $findings[] = $this->logged([
                'check' => __('marketplace.wallet.verify.findings.unassigned_difference.check'),
                'subject' => __('marketplace.wallet.verify.findings.unassigned_difference.subject'),
                'detail' => __('marketplace.wallet.verify.findings.unassigned_difference.detail', [
                    'difference' => $this->formatMoney($difference - $assigned),
                ]),
            ]);
        }

        return $findings;
    }

    /**
     * Protokolliert einen Reservierungsbefund und gibt ihn zurueck. Das
     * Protokoll ist die vollstaendige Fassung: In Konsole und Mail steht je
     * Wallet nur der Deckel, hier steht jede Zeile.
     *
     * @param  array{check: string, subject: string, detail: string}  $finding
     * @return array{check: string, subject: string, detail: string}
     */
    private function logged(array $finding): array
    {
        Log::error('Reservierung ohne Deckung in den offenen Leadkaeufen.', $finding);

        return $finding;
    }

    /**
     * Ebene 2: Kaeufer-Wallets, deren `reserved_cents` nicht zur Summe ihrer
     * offenen Leadkaeufe passt -- eine Abfrage, nur die Abweichler.
     *
     * Treiber sind die Wallets, nicht die Kaeufe: Ein Wallet mit Reservierung
     * und ohne offenen Kauf ist genau der Fall, den diese Ebene sucht, und
     * waere auf der anderen Seite des Joins unsichtbar.
     *
     * Preis UND Aufschlag, Begruendung siehe checkReservationTotals().
     *
     * @return list<array{wallet_id: int, owner: string, expected: int, actual: int, count: int}>
     */
    private function walletsWithReservationGap(): array
    {
        $openPurchases = LeadPurchase::query()
            ->where('status', PurchaseStatus::RESERVED->value)
            ->groupBy('buyer_tenant_id')
            ->selectRaw('buyer_tenant_id')
            ->selectRaw('SUM(price_cents + surcharge_cents) AS reserved_expected')
            ->selectRaw('COUNT(*) AS purchase_count');

        $rows = Wallet::query()
            ->where('wallets.owner_type', WalletOwnerType::BUYER->value)
            ->leftJoinSub($openPurchases, 'open_purchases', 'open_purchases.buyer_tenant_id', '=', 'wallets.owner_id')
            ->whereRaw('wallets.reserved_cents <> COALESCE(open_purchases.reserved_expected, 0)')
            ->orderBy('wallets.id')
            ->select('wallets.*')
            ->selectRaw('COALESCE(open_purchases.reserved_expected, 0) AS reserved_expected')
            ->selectRaw('COALESCE(open_purchases.purchase_count, 0) AS purchase_count')
            ->get();

        return $rows->map(fn (Wallet $wallet): array => [
            'wallet_id' => (int) $wallet->getKey(),
            'owner' => $this->describeOwner($wallet),
            'expected' => (int) $wallet->getAttribute('reserved_expected'),
            'actual' => $wallet->reserved_cents,
            'count' => (int) $wallet->getAttribute('purchase_count'),
        ])->values()->all();
    }

    /**
     * Ebene 3: je Kaufbeleg, nur fuer die auffaelligen Wallets. Zwei Abfragen --
     * die `reserve`- und `release`-Zeilen dieser Wallets (Netto je Beleg in
     * PHP, damit kein Datenbankdialekt im Code steht) und die zugehoerigen
     * Kaufbelege.
     *
     * `capture` entlastet nicht: Nach
     * WalletTransactionType::affectsReservedBalance() bewegt nur `release` den
     * reservierten Betrag. Ein Beleg mit `capture`, aber ohne `release`
     * blockiert weiter Guthaben -- ihn als erledigt zu zaehlen, verdeckte genau
     * den Fall, den diese Ebene sucht.
     *
     * @param  list<int>  $walletIds
     * @return array<int, list<array{check: string, subject: string, detail: string}>>
     */
    private function purchaseReservationFindings(array $walletIds): array
    {
        /** @var array<int, array<int, array{net: int, entry: int|null, date: ?Carbon}>> $perPurchase */
        $perPurchase = [];
        /** @var array<int, array{net: int, entries: list<int>}> $unassigned */
        $unassigned = [];

        WalletTransaction::query()
            ->whereIn('wallet_id', $walletIds)
            ->whereIn('type', [
                WalletTransactionType::RESERVE->value,
                WalletTransactionType::RELEASE->value,
            ])
            ->chunkById(self::CHUNK_SIZE, function (Collection $entries) use (&$perPurchase, &$unassigned): void {
                foreach ($entries as $entry) {
                    $walletId = (int) $entry->wallet_id;
                    $purchaseId = $this->purchaseIdFor($entry);

                    if ($purchaseId === null) {
                        $unassigned[$walletId] ??= ['net' => 0, 'entries' => []];
                        $unassigned[$walletId]['net'] += $entry->amount_cents;
                        $unassigned[$walletId]['entries'][] = (int) $entry->getKey();

                        continue;
                    }

                    $perPurchase[$walletId][$purchaseId] ??= ['net' => 0, 'entry' => null, 'date' => null];
                    $perPurchase[$walletId][$purchaseId]['net'] += $entry->amount_cents;

                    // Aelteste reserve-Zeile: Sie ist der Anfang, den ein
                    // Mensch sucht, und chunkById laeuft nach `id` aufwaerts.
                    if ($entry->type === WalletTransactionType::RESERVE && $perPurchase[$walletId][$purchaseId]['entry'] === null) {
                        $perPurchase[$walletId][$purchaseId]['entry'] = (int) $entry->getKey();
                        $perPurchase[$walletId][$purchaseId]['date'] = $entry->created_at;
                    }
                }
            });

        $purchaseIds = [];

        foreach ($perPurchase as $byPurchase) {
            $purchaseIds = [...$purchaseIds, ...array_keys($byPurchase)];
        }

        $purchases = $purchaseIds === []
            ? collect()
            : LeadPurchase::query()
                ->whereIn('id', $purchaseIds)
                ->get(['id', 'status', 'price_cents', 'surcharge_cents'])
                ->keyBy('id');

        $findings = [];

        foreach ($walletIds as $walletId) {
            $rows = [];

            foreach ($perPurchase[$walletId] ?? [] as $purchaseId => $state) {
                /** @var LeadPurchase|null $purchase */
                $purchase = $purchases->get($purchaseId);

                $expected = $purchase !== null && $purchase->status === PurchaseStatus::RESERVED
                    ? $purchase->price_cents + $purchase->surcharge_cents
                    : 0;

                if ($state['net'] === $expected) {
                    continue;
                }

                $rows[] = [
                    'sort' => $state['date']?->getTimestamp() ?? 0,
                    'finding' => $this->purchaseFinding($walletId, (int) $purchaseId, $purchase, $state, $expected),
                ];
            }

            // Aeltestes zuerst: Wer sucht, will den Anfang.
            usort($rows, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

            $wallet = array_map(static fn (array $row): array => $row['finding'], $rows);

            if (($unassigned[$walletId]['net'] ?? 0) !== 0) {
                $entries = $unassigned[$walletId]['entries'];

                $wallet[] = [
                    'check' => __('marketplace.wallet.verify.findings.purchase.unassigned.check'),
                    'subject' => __('marketplace.wallet.verify.mail.wallet', ['id' => $walletId]),
                    'detail' => __('marketplace.wallet.verify.findings.purchase.unassigned.detail', [
                        'amount' => $this->formatMoney($unassigned[$walletId]['net']),
                        'count' => (string) count($entries),
                        'entries' => implode(', ', array_map(static fn (int $id): string => '#'.$id, $entries)),
                    ]),
                ];
            }

            foreach ($wallet as $finding) {
                $this->logged($finding);
            }

            // Deckel: Eine Mail mit vierhundert Zeilen liest niemand.
            // Vollstaendig bleibt der Befund im Protokoll darueber.
            if (count($wallet) > self::PURCHASE_FINDING_LIMIT) {
                $hidden = count($wallet) - self::PURCHASE_FINDING_LIMIT;
                $wallet = array_slice($wallet, 0, self::PURCHASE_FINDING_LIMIT);

                $wallet[] = [
                    'check' => __('marketplace.wallet.verify.findings.purchase.more.check'),
                    'subject' => __('marketplace.wallet.verify.mail.wallet', ['id' => $walletId]),
                    'detail' => __('marketplace.wallet.verify.findings.purchase.more.detail', [
                        'count' => (string) $hidden,
                    ]),
                ];
            }

            if ($wallet !== []) {
                $findings[$walletId] = $wallet;
            }
        }

        return $findings;
    }

    /**
     * Vier Ausfallbilder, vier Texte -- jeder mit Journalzeile und Datum, den
     * zwei Angaben, mit denen ein Mensch die Zeile ohne weitere Abfrage findet.
     *
     * @param  array{net: int, entry: int|null, date: ?Carbon}  $state
     * @return array{check: string, subject: string, detail: string}
     */
    private function purchaseFinding(int $walletId, int $purchaseId, ?LeadPurchase $purchase, array $state, int $expected): array
    {
        $subject = __('marketplace.wallet.verify.findings.purchase.subject', [
            'purchase' => (string) $purchaseId,
            'wallet' => (string) $walletId,
        ]);

        $entry = $state['entry'] === null ? '?' : (string) $state['entry'];
        $date = $state['date']?->format('d.m.Y H:i') ?? '?';

        if ($purchase === null) {
            return [
                'check' => __('marketplace.wallet.verify.findings.purchase.missing.check'),
                'subject' => $subject,
                'detail' => __('marketplace.wallet.verify.findings.purchase.missing.detail', [
                    'entry' => $entry,
                    'amount' => $this->formatMoney($state['net']),
                    'date' => $date,
                ]),
            ];
        }

        if ($purchase->status !== PurchaseStatus::RESERVED) {
            return [
                'check' => __('marketplace.wallet.verify.findings.purchase.settled.check'),
                'subject' => $subject,
                'detail' => __('marketplace.wallet.verify.findings.purchase.settled.detail', [
                    'entry' => $entry,
                    'amount' => $this->formatMoney($state['net']),
                    'date' => $date,
                    'status' => $purchase->status->value,
                ]),
            ];
        }

        return [
            'check' => __('marketplace.wallet.verify.findings.purchase.amount.check'),
            'subject' => $subject,
            'detail' => __('marketplace.wallet.verify.findings.purchase.amount.detail', [
                'expected' => $this->formatMoney($expected),
                'actual' => $this->formatMoney($state['net']),
                'difference' => $this->formatMoney($state['net'] - $expected),
                'entry' => $entry,
                'date' => $date,
            ]),
        ];
    }

    /**
     * Welchen Kaufbeleg traegt eine Journalzeile?
     *
     * Erst der Beleg selbst, sonst der mittlere Teil des Idempotenzschluessels
     * (Konvention aus WalletService::keyFor()). Die Schluesselauswertung ist
     * nicht optional: Die Gegenbuchung einer belegfreien Reservierung traegt
     * bewusst `reference: null` und heisst `release:lead_purchase:<id>:orphan`.
     * Ohne sie erschiene die ausgeglichene Zeile dauerhaft als Befund.
     */
    private function purchaseIdFor(WalletTransaction $entry): ?int
    {
        if ($entry->reference_type === LeadPurchase::class && $entry->reference_id !== null) {
            return (int) $entry->reference_id;
        }

        if ($entry->idempotency_key === null) {
            return null;
        }

        $segment = preg_quote(Str::snake(class_basename(LeadPurchase::class)), '/');

        if (preg_match('/^[a-z_]+:'.$segment.':(\d+)(?::|$)/', $entry->idempotency_key, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Dritte Klammer: die Regeln, die Pay as you go ueber das Journal hinaus
     * setzt (LP-POSTPAID-013).
     *
     * Fuenf Invarianten, jede mit ihrem eigenen Ausfallbild:
     *
     * 1. Ein Prepaid-Wallet darf nicht im Minus stehen. Legitime Ausnahmen sind
     *    Ruecklastschrift und Chargeback (Buchung `adjustment`), die
     *    Rueckstufungsgebuehr (`fee`) und der Nachlauf einer Rueckstufung: Ein
     *    Kauf, der vor der Rueckstufung reserviert wurde, wird danach noch
     *    abgebucht und fuehrt den Saldo bewusst ins Minus. Daran erkennbar,
     *    dass `postpaid_disabled_at` steht.
     * 2. Ein Postpaid-Wallet darf offenen Betrag und Reservierungen zusammen
     *    nicht ueber den Kreditrahmen fuehren; das ist dieselbe Rechnung wie
     *    `available_cents >= 0`, nur von der anderen Seite. Sie schlaegt an,
     *    wenn ein Kauf an der Deckungspruefung vorbeigelaufen ist oder ein
     *    Rahmen unter den bereits ausgeschoepften Betrag gesenkt wurde.
     * 3. Je Wallet darf es hoechstens einen unentschiedenen Einzug geben
     *    (`pending`, `processing`, `retry_pending`). Zwei offene Settlements
     *    ziehen denselben offenen Betrag zweimal ein.
     * 4. Zu jedem bezahlten Einzug gehoert genau die Gutschrift im Journal, die
     *    ihn traegt -- eine Buchung vom Typ `settlement` mit dem
     *    Idempotenzschluessel aus WalletService::keyFor(). Fehlt sie, ist beim
     *    Kaeufer Geld eingezogen, ohne dass sein Saldo es gesehen hat.
     * 5. Die Aufschlagszeilen auf dem Plattform-Wallet muessen der Summe der
     *    `surcharge_cents` aller abgerechneten Postpaid-Kaeufe entsprechen.
     *    Erstattete Kaeufe heben sich im Journal selbst auf (Buchung und
     *    Gegenbuchung) und stehen nicht mehr auf `captured` -- beide Seiten
     *    lassen sie also aus.
     *
     * Repariert wird hier nichts: Anders als ein abgelaufener Zwischenstand ist
     * eine verletzte Invariante ein Fehler im Ablauf, den erst ein Mensch
     * einordnen muss.
     *
     * @return list<array{check: string, subject: string, detail: string}>
     */
    private function checkPostpaidInvariants(): array
    {
        $findings = [
            ...$this->checkNegativePrepaidWallets(),
            ...$this->checkCreditLimits(),
            ...$this->checkOpenSettlements(),
            ...$this->checkPaidSettlementLedger(),
            ...$this->checkSurchargeTotals(),
        ];

        foreach ($findings as $finding) {
            Log::error('Postpaid-Invariante verletzt.', $finding);
        }

        return $findings;
    }

    /**
     * Invariante 1: negatives Guthaben ohne Postpaid-Vergangenheit.
     *
     * @return list<array{check: string, subject: string, detail: string}>
     */
    private function checkNegativePrepaidWallets(): array
    {
        $wallets = Wallet::query()
            ->where('payment_mode', PaymentMode::PREPAID->value)
            ->where('balance_cents', '<', 0)
            ->whereNull('postpaid_disabled_at')
            ->whereDoesntHave('transactions', function (Builder $query): void {
                $query->whereIn('type', [
                    WalletTransactionType::ADJUSTMENT->value,
                    WalletTransactionType::FEE->value,
                ]);
            })
            ->orderBy('id')
            ->get();

        return $wallets->map(fn (Wallet $wallet): array => [
            'check' => 'Prepaid im Minus',
            'subject' => sprintf('Wallet #%s (%s)', $wallet->getKey(), $this->describeOwner($wallet)),
            'detail' => sprintf(
                'Saldo %s ohne Korrektur-, Gebuehren- oder Rueckstufungshistorie.',
                $this->formatMoney($wallet->balance_cents),
            ),
        ])->values()->all();
    }

    /**
     * Invariante 2: offener Betrag plus Reservierungen ueber dem Kreditrahmen.
     *
     * @return list<array{check: string, subject: string, detail: string}>
     */
    private function checkCreditLimits(): array
    {
        $wallets = Wallet::query()
            ->where('payment_mode', PaymentMode::POSTPAID->value)
            ->whereRaw('(reserved_cents - balance_cents) > credit_limit_cents')
            ->orderBy('id')
            ->get();

        return $wallets->map(fn (Wallet $wallet): array => [
            'check' => 'Kreditrahmen ueberschritten',
            'subject' => sprintf('Wallet #%s (%s)', $wallet->getKey(), $this->describeOwner($wallet)),
            'detail' => sprintf(
                'Offen %s zuzueglich reserviert %s uebersteigt den Rahmen %s.',
                $this->formatMoney($wallet->open_amount_cents),
                $this->formatMoney($wallet->reserved_cents),
                $this->formatMoney($wallet->credit_limit_cents),
            ),
        ])->values()->all();
    }

    /**
     * Invariante 3: mehr als ein unentschiedener Einzug je Wallet.
     *
     * @return list<array{check: string, subject: string, detail: string}>
     */
    private function checkOpenSettlements(): array
    {
        $rows = Settlement::query()
            ->unresolved()
            ->groupBy('wallet_id')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('wallet_id, COUNT(*) AS open_count')
            // toBase(): Das Ergebnis sind Aggregate, keine Einzuege.
            ->toBase()
            ->get();

        $findings = [];

        foreach ($rows as $row) {
            $findings[] = [
                'check' => 'Mehrere offene Einzuege',
                'subject' => sprintf('Wallet #%s', (int) $row->wallet_id),
                'detail' => sprintf(
                    '%d Settlements in pending, processing oder retry_pending -- der offene Betrag wuerde mehrfach eingezogen.',
                    (int) $row->open_count,
                ),
            ];
        }

        return $findings;
    }

    /**
     * Invariante 4: bezahlter Einzug ohne die Gutschrift im Journal.
     *
     * @return list<array{check: string, subject: string, detail: string}>
     */
    private function checkPaidSettlementLedger(): array
    {
        $findings = [];

        Settlement::query()
            ->where('status', SettlementStatus::PAID->value)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $settlements) use (&$findings): void {
                $keys = $settlements
                    ->mapWithKeys(fn (Settlement $settlement): array => [
                        WalletService::keyFor(WalletTransactionType::SETTLEMENT, $settlement) => $settlement,
                    ]);

                $booked = WalletTransaction::query()
                    ->whereIn('idempotency_key', $keys->keys()->all())
                    ->pluck('idempotency_key')
                    ->all();

                foreach (array_diff($keys->keys()->all(), $booked) as $missing) {
                    /** @var Settlement $settlement */
                    $settlement = $keys[$missing];

                    $findings[] = [
                        'check' => 'Einzug ohne Buchung',
                        'subject' => sprintf('Settlement #%s (Wallet #%d)', $settlement->getKey(), $settlement->wallet_id),
                        'detail' => sprintf(
                            'Stand paid ueber %s, aber keine Buchung mit dem Schluessel "%s".',
                            $this->formatMoney($settlement->amount_cents),
                            $missing,
                        ),
                    ];
                }
            });

        return $findings;
    }

    /**
     * Invariante 5: Aufschlagszeilen der Plattform gegen die abgerechneten
     * Postpaid-Kaeufe.
     *
     * @return list<array{check: string, subject: string, detail: string}>
     */
    private function checkSurchargeTotals(): array
    {
        $booked = (int) WalletTransaction::query()
            ->where('wallet_id', Wallet::forPlatform()->getKey())
            ->where('type', WalletTransactionType::SURCHARGE->value)
            ->sum('amount_cents');

        $expected = (int) LeadPurchase::query()
            ->where('status', PurchaseStatus::CAPTURED->value)
            ->where('payment_mode', PaymentMode::POSTPAID->value)
            ->sum('surcharge_cents');

        if ($booked === $expected) {
            return [];
        }

        return [[
            'check' => 'Aufschlaege stimmen nicht',
            'subject' => 'Plattform-Wallet',
            'detail' => sprintf(
                'Gebucht %s, aus abgerechneten Postpaid-Kaeufen erwartet %s (Differenz %s).',
                $this->formatMoney($booked),
                $this->formatMoney($expected),
                $this->formatMoney($booked - $expected),
            ),
        ]];
    }

    /**
     * @param  list<array{wallet_id: int, owner: string, balance_expected: int, balance_actual: int, reserved_expected: int, reserved_actual: int, repaired: bool}>  $mismatches
     * @param  array{expected: int, actual: int}|null  $reservationTotals
     * @param  list<array{check: string, subject: string, detail: string}>  $findings
     * @param  bool  $reservationHint  Mindestens ein Reservierungsbefund -- dann steht der Hinweis unter der Tabelle
     */
    private function renderSummary(array $mismatches, ?array $reservationTotals, array $findings, int $checked, bool $reservationHint = false): void
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

        if ($findings !== []) {
            $this->table(
                ['Pruefung', 'Betroffen', 'Befund'],
                array_map(static fn (array $finding): array => [
                    $finding['check'],
                    $finding['subject'],
                    $finding['detail'],
                ], $findings),
            );

            $this->error(sprintf('%d Befund(e).', count($findings)));
        }

        // Einmal unter der Tabelle, nicht als Zeile darin: Repariert wird eine
        // belegfreie Reservierung nie, sie wird gegengebucht.
        if ($reservationHint) {
            $this->warn(__('marketplace.wallet.verify.findings.hint'));
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
     * @param  list<array{check: string, subject: string, detail: string}>  $findings
     */
    private function notify(array $mismatches, ?array $reservationTotals, array $findings, int $checked, bool $reservationHint = false): void
    {
        $recipient = $this->support->address();

        if ($recipient === null) {
            $this->warn('Keine Meldung verschickt: app.support_email ist nicht brauchbar gesetzt (leer, ungueltig oder Starterkit-Domain).');

            return;
        }

        Mail::to($recipient)->send(new WalletLedgerMismatch($mismatches, $reservationTotals, $checked, $findings, $reservationHint));
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
