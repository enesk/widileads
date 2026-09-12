<?php

declare(strict_types=1);

namespace App\Services\Wallet;

use App\Constants\WalletOwnerType;
use App\Constants\WalletTransactionType;
use App\Events\Wallet\WalletUnblocked;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\PurchaseBlockedException;
use App\Models\LeadPurchase;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Die einzige Schreibstelle des Wallet-Ledgers (LP-WALLET-005).
 *
 * Jede Geldbewegung des Marktplatzes laeuft ueber post(): Aufladung,
 * Reservierung, Abbuchung, Provision, Auszahlung, Korrektur. Kein anderer Pfad
 * schreibt `wallets.balance_cents` oder `wallets.reserved_cents` -- ein Saldo
 * ohne Buchung waere Geld ohne Beleg, und dann ist nicht mehr feststellbar,
 * welcher Wert stimmt.
 *
 * Ablauf jeder Buchung, unteilbar in einer Transaktion:
 *
 * 1. Wallet-Zeile mit `lockForUpdate()` frisch laden. Erst danach wird
 *    gerechnet -- der Saldo aus dem uebergebenen Model kann veraltet sein.
 * 2. Idempotenzschluessel pruefen. Gibt es die Buchung schon, wird sie
 *    zurueckgegeben und nichts erneut gebucht.
 * 3. Deckung pruefen (siehe unten).
 * 4. Saldo fortschreiben und die Buchung mit beiden Staenden danach schreiben.
 *
 * Die Sperre auf der Wallet-Zeile genuegt: Alle Buchungen desselben Wallets
 * laufen dadurch nacheinander, egal aus welchem Prozess sie kommen. Zwei
 * gleichzeitige Reservierungen auf knappe Deckung koennen so nicht gemeinsam
 * durchgehen -- die zweite wartet und sieht den bereits geblockten Betrag.
 *
 * Zwei Salden, jede Buchung wirkt auf genau einen: `reserve` und `release`
 * bewegen `reserved_cents`, alle uebrigen Arten `balance_cents`
 * (WalletTransactionType::balanceColumn()). `amount_cents` traegt das
 * Vorzeichen, das die Buchungsart verlangt.
 *
 * Deckungsregeln:
 * - `reserve` braucht freies Guthaben (`available_cents`), nicht nur Saldo.
 *   Bei einem Postpaid-Kaeufer (LP-POSTPAID-004) steckt der Kreditrahmen in
 *   ebendiesem Wert -- der Service rechnet deshalb unveraendert weiter, ohne
 *   den Zahlungsmodus zu kennen.
 * - `release` kann nicht mehr aufloesen, als reserviert ist.
 * - Jede andere Buchung darf den Saldo nicht unter null druecken. Ausnahmen
 *   nur mit ausdruecklichem `allowNegative` und nur, wo die Buchungsart es
 *   zulaesst (WalletTransactionType::allowsNegativeBalance()): die manuelle
 *   Korrektur des Admins und die Rueckbuchung einer Einnahme oder Provision
 *   bei einer Erstattung.
 * - `capture` auf einem Kauf-Wallet darf den Saldo ins Minus druecken, wenn
 *   der zugehoerige Leadkauf ein Postpaid-Kauf war -- das ist der Regelfall
 *   von Pay as you go und braucht kein `allowNegative` des Aufrufers.
 *
 * Zwei Sonderregeln von Pay as you go (LP-POSTPAID-004):
 *
 * - Ist `purchase_blocked` gesetzt, wird keine Reservierung mehr angenommen
 *   (PurchaseBlockedException). Gutschriften, Einzuege und Korrekturen bleiben
 *   moeglich, sonst liesse sich die Sperre nie wieder aufheben.
 * - Nach jeder Buchung auf den freien Saldo wird die Sperre aufgehoben, sobald
 *   der Saldo wieder bei null oder darueber steht (Ereignis WalletUnblocked).
 */
class WalletService
{
    /**
     * Schreibt eine Buchung und schreibt den betroffenen Saldo fort.
     *
     * @param  Model|null  $reference  Beleg der Buchung: Leadkauf, Bestellung, Auszahlungsanforderung
     * @param  string|null  $idempotencyKey  Klammer gegen Doppelbuchung, Konvention siehe keyFor()
     * @param  array<string, mixed>  $meta  Zusatzangaben (Provisionssatz, Grund einer Korrektur)
     * @param  bool  $allowNegative  Laesst den Saldo ins Minus laufen, soweit die Buchungsart es zulaesst
     * @param  int|null  $createdBy  Der Admin, der eine manuelle Korrektur gebucht hat
     *
     * @throws InvalidArgumentException bei einem Vorzeichen, das nicht zur Buchungsart passt
     * @throws InsufficientFundsException wenn die Deckung nicht reicht
     * @throws PurchaseBlockedException wenn das Konto des Kaeufers gesperrt ist
     */
    public function post(
        Wallet $wallet,
        WalletTransactionType $type,
        int $amountCents,
        string $description,
        ?Model $reference = null,
        ?string $idempotencyKey = null,
        array $meta = [],
        bool $allowNegative = false,
        ?int $createdBy = null,
    ): WalletTransaction {
        if (! $type->allowsAmount($amountCents)) {
            throw new InvalidArgumentException(
                sprintf('Eine Buchung vom Typ "%s" laesst den Betrag %d nicht zu.', $type->value, $amountCents),
            );
        }

        if ($allowNegative && ! $type->allowsNegativeBalance($amountCents)) {
            throw new InvalidArgumentException(
                sprintf('Eine Buchung vom Typ "%s" darf den Saldo nicht ins Minus druecken.', $type->value),
            );
        }

        // Ausserhalb der Transaktion: erspart im Wiederholungsfall die Sperre.
        // Die verbindliche Pruefung passiert unter Sperre noch einmal.
        $existing = $this->existingFor($idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use (
            $wallet,
            $type,
            $amountCents,
            $description,
            $reference,
            $idempotencyKey,
            $meta,
            $allowNegative,
            $createdBy,
        ): WalletTransaction {
            // Frisch und gesperrt: der Saldo des uebergebenen Models kann
            // veraltet sein, gerechnet wird nur mit diesem Stand.
            $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            $existing = $this->existingFor($idempotencyKey);

            if ($existing !== null) {
                return $existing;
            }

            $this->guardFunds($locked, $type, $amountCents, $allowNegative, $reference);

            if ($type->affectsReservedBalance()) {
                $locked->reserved_cents += $amountCents;
            } else {
                $locked->balance_cents += $amountCents;
            }

            $locked->save();

            $this->releaseBlockIfSettled($locked, $type);

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $locked->getKey(),
                'type' => $type,
                'amount_cents' => $amountCents,
                'balance_after_cents' => $locked->balance_cents,
                'reserved_after_cents' => $locked->reserved_cents,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'meta' => $meta === [] ? null : $meta,
                'created_by' => $createdBy,
            ]);

            // Das uebergebene Model traegt danach den tatsaechlichen Stand --
            // der Aufrufer soll nicht mit veralteten Salden weiterarbeiten.
            $wallet->setRawAttributes($locked->getAttributes(), true);

            return $transaction;
        });
    }

    /**
     * Setzt die fortgeschriebenen Salden eines Wallets auf den Stand seines
     * Journals (LP-WALLET-015).
     *
     * Die einzige Ausnahme von der Regel, dass nur post() die Saldenspalten
     * schreibt -- und sie bricht sie nicht: Hier entsteht kein Geld und keine
     * Buchung, hier wird ein abgewichener Zwischenstand auf die Wahrheit
     * zurueckgesetzt. Deshalb steht die Methode hier und nicht im Befehl: Die
     * Sperre auf der Wallet-Zeile und das Nachrechnen unter Sperre gehoeren
     * zusammen, sonst korrigiert der Lauf gegen einen Stand, der sich gerade
     * aendert.
     *
     * Aufgerufen wird sie ausschliesslich vom Befehl `wallet:verify --repair`
     * und nur nach ausdruecklicher Freigabe; jede Korrektur wird protokolliert.
     *
     * @return array{before: array{balance_cents: int, reserved_cents: int}, after: array{balance_cents: int, reserved_cents: int}, changed: bool}
     */
    public function resyncFromLedger(Wallet $wallet): array
    {
        return DB::transaction(function () use ($wallet): array {
            $locked = Wallet::query()->whereKey($wallet->getKey())->lockForUpdate()->firstOrFail();

            // Unter Sperre neu gerechnet: Zwischen der Pruefung des Befehls und
            // dieser Korrektur kann eine Buchung gelaufen sein.
            $totals = WalletTransaction::query()->sumsByWallet([$locked->getKey()])[$locked->getKey()]
                ?? ['balance_cents' => 0, 'reserved_cents' => 0];

            $before = [
                'balance_cents' => $locked->balance_cents,
                'reserved_cents' => $locked->reserved_cents,
            ];

            $changed = $before !== $totals;

            if ($changed) {
                $locked->balance_cents = $totals['balance_cents'];
                $locked->reserved_cents = $totals['reserved_cents'];
                $locked->save();

                Log::warning('Wallet-Saldo auf den Ledger-Stand zurueckgesetzt.', [
                    'wallet_id' => $locked->getKey(),
                    'ist' => $before,
                    'soll' => $totals,
                ]);
            }

            $wallet->setRawAttributes($locked->getAttributes(), true);

            return ['before' => $before, 'after' => $totals, 'changed' => $changed];
        });
    }

    /**
     * Idempotenzschluessel nach der Konvention '{type}:{beleg}:{id}', etwa
     * 'capture:lead_purchase:4711'. Damit ist ein doppelter Listener-Lauf
     * harmlos: die zweite Buchung findet die erste und bucht nichts.
     */
    public static function keyFor(WalletTransactionType $type, Model $reference, ?string $suffix = null): string
    {
        $key = sprintf(
            '%s:%s:%s',
            $type->value,
            Str::snake(class_basename($reference)),
            (string) $reference->getKey(),
        );

        return $suffix === null ? $key : $key.':'.$suffix;
    }

    /**
     * Deckungspruefung auf dem gesperrten Stand.
     *
     * @param  Model|null  $reference  Beleg der Buchung; entscheidet bei `capture`, ob der Saldo ins Minus darf
     *
     * @throws InsufficientFundsException
     * @throws PurchaseBlockedException
     */
    private function guardFunds(Wallet $wallet, WalletTransactionType $type, int $amountCents, bool $allowNegative, ?Model $reference = null): void
    {
        if ($type === WalletTransactionType::RESERVE) {
            if ($wallet->purchase_blocked) {
                throw PurchaseBlockedException::forWallet($wallet);
            }

            // `available_cents` traegt den Kreditrahmen bereits in sich
            // (LP-POSTPAID-004): Bei Prepaid ist er 0, bei Postpaid ist er der
            // Betrag, den der Kaeufer zusaetzlich ausgeben darf.
            if ($wallet->available_cents < $amountCents) {
                throw InsufficientFundsException::forReservation($wallet, $amountCents);
            }

            return;
        }

        if ($type === WalletTransactionType::RELEASE) {
            if ($wallet->reserved_cents + $amountCents < 0) {
                throw InsufficientFundsException::forRelease($wallet, $amountCents);
            }

            return;
        }

        if ($allowNegative || $this->isCoveredByCredit($wallet, $type, $reference)) {
            return;
        }

        if ($wallet->balance_cents + $amountCents < 0) {
            throw InsufficientFundsException::forBalance($wallet, $amountCents);
        }
    }

    /**
     * Darf diese Abbuchung den Saldo des Kaeufers ins Minus druecken?
     *
     * Nur `capture` auf einem Kauf-Wallet, und nur fuer einen Postpaid-Kauf.
     * Massgeblich ist der Zahlungsmodus, der am Kaufbeleg festgeschrieben ist,
     * nicht der aktuelle Modus des Wallets: Ein Kaeufer kann zwischen Kauf und
     * Abrechnung zurueckgestuft worden sein (LP-POSTPAID-009). Wuerde hier der
     * aktuelle Modus zaehlen, blieben seine laufenden Leads unabrechenbar --
     * der Verkaeufer bekaeme sein Geld nicht, obwohl der Lead erreichbar war.
     *
     * Umgekehrt genuegt der Modus des Wallets, wenn kein Kaufbeleg vorliegt:
     * Ein Postpaid-Kaeufer soll an keiner Abbuchung scheitern, die er im
     * Rahmen seines Kredits ausgeloest hat.
     *
     * Eine Obergrenze wird hier bewusst nicht noch einmal geprueft. Die
     * Reservierung hat bereits gegen den damaligen Rahmen geprueft; eine
     * zweite Pruefung zum Abrechnungszeitpunkt wuerde einen laengst
     * genehmigten Kauf nachtraeglich platzen lassen.
     */
    private function isCoveredByCredit(Wallet $wallet, WalletTransactionType $type, ?Model $reference): bool
    {
        if ($type !== WalletTransactionType::CAPTURE) {
            return false;
        }

        if ($wallet->owner_type !== WalletOwnerType::BUYER) {
            return false;
        }

        // Der Kaufbeleg entscheidet, sofern sein Modus vorliegt. Traegt ein
        // Bestandsbeleg die Spalte nicht im Arbeitsspeicher, faellt die
        // Entscheidung auf den Modus des Wallets zurueck.
        if ($reference instanceof LeadPurchase && $reference->payment_mode !== null) {
            return $reference->payment_mode->allowsCredit();
        }

        return $wallet->isPostpaid();
    }

    /**
     * Hebt die Kaufsperre auf, sobald der offene Betrag ausgeglichen ist
     * (LP-POSTPAID-004).
     *
     * Laeuft in derselben Transaktion wie die Buchung, die den Saldo bewegt
     * hat -- Zahlung und Entsperrung gehoeren zusammen. Nur Buchungen auf den
     * freien Saldo koennen etwas ausgleichen; eine Reservierung bewegt den
     * offenen Betrag nicht.
     */
    private function releaseBlockIfSettled(Wallet $wallet, WalletTransactionType $type): void
    {
        if ($type->affectsReservedBalance()) {
            return;
        }

        if (! $wallet->purchase_blocked || $wallet->balance_cents < 0) {
            return;
        }

        $wallet->purchase_blocked = false;
        $wallet->save();

        WalletUnblocked::dispatch($wallet->getKey(), $wallet->owner_id, $wallet->balance_cents);
    }

    /**
     * Buchung zu diesem Schluessel, falls es sie schon gibt. Der Unique-Index
     * auf `idempotency_key` ist die eigentliche Absicherung; diese Abfrage
     * erspart nur den Fehlerfall im Regelbetrieb.
     */
    private function existingFor(?string $idempotencyKey): ?WalletTransaction
    {
        if ($idempotencyKey === null) {
            return null;
        }

        return WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
    }
}
