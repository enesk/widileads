<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Constants\WalletTransactionType;
use App\Exceptions\ImmutableLedgerException;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query-Builder des Wallet-Ledgers (LP-WALLET-004). Er schliesst die Luecke, die
 * Model-Events offenlassen: Massen-Updates und -Loeschungen ueber den Builder
 * loesen keine Model-Events aus und werden deshalb hier abgefangen.
 *
 * Gleiches Muster wie LeadStateLogQueryBuilder (FB-030).
 *
 * @extends Builder<WalletTransaction>
 */
class WalletTransactionQueryBuilder extends Builder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        throw ImmutableLedgerException::forUpdate();
    }

    public function delete(): mixed
    {
        throw ImmutableLedgerException::forDeletion();
    }

    public function forceDelete(): mixed
    {
        throw ImmutableLedgerException::forDeletion();
    }

    public function truncate(): void
    {
        throw ImmutableLedgerException::forDeletion();
    }

    /**
     * Sollsalden aus dem Journal, je Wallet in einer Abfrage aggregiert
     * (LP-WALLET-015).
     *
     * Das Journal ist die Wahrheit, `wallets.balance_cents` und
     * `wallets.reserved_cents` sind nur fortgeschriebene Zwischenstaende.
     * Getrennt wird nach der Spalte, auf die eine Buchungsart wirkt:
     * `reserve` und `release` bewegen den reservierten Betrag, alle uebrigen
     * den freien Saldo (WalletTransactionType::affectsReservedBalance()).
     *
     * Wallets ohne Buchung fehlen im Ergebnis -- ihr Soll ist null.
     *
     * @param  list<int>  $walletIds
     * @return array<int, array{balance_cents: int, reserved_cents: int}>
     */
    public function sumsByWallet(array $walletIds): array
    {
        if ($walletIds === []) {
            return [];
        }

        $reservedTypes = array_values(array_map(
            static fn (WalletTransactionType $type): string => $type->value,
            array_filter(
                WalletTransactionType::cases(),
                static fn (WalletTransactionType $type): bool => $type->affectsReservedBalance(),
            ),
        ));

        $placeholders = implode(', ', array_fill(0, count($reservedTypes), '?'));

        $rows = $this->whereIn('wallet_id', $walletIds)
            ->groupBy('wallet_id')
            ->selectRaw('wallet_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ($placeholders) THEN amount_cents ELSE 0 END), 0) AS reserved_total", $reservedTypes)
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ($placeholders) THEN 0 ELSE amount_cents END), 0) AS balance_total", $reservedTypes)
            // toBase(): Das Ergebnis sind Aggregate, keine Buchungen -- als
            // Model geladen taeuschten sie Zeilen vor, die es nicht gibt.
            ->toBase()
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->wallet_id] = [
                'balance_cents' => (int) $row->balance_total,
                'reserved_cents' => (int) $row->reserved_total,
            ];
        }

        return $totals;
    }
}
