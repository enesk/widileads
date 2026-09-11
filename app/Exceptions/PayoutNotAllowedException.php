<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Constants\PayoutStatus;
use App\Models\PayoutRequest;
use RuntimeException;

/**
 * Eine Auszahlung ist so nicht moeglich (LP-WALLET-010).
 *
 * Drei Faelle, die der Verkaeufer selbst aufloesen kann -- fehlende
 * Bankverbindung, Betrag unter dem Mindestbetrag, Betrag ueber dem Guthaben --
 * und einer, der ein Fehler im Aufrufer ist: eine bereits entschiedene
 * Anforderung noch einmal zu entscheiden. Letzteres waere gefaehrlich, weil
 * eine zweite Ablehnung den Betrag ein zweites Mal zurueckbuchen wuerde; der
 * Idempotenzschluessel der Buchung faengt das zwar ab, aber der Aufrufer soll
 * es merken.
 *
 * Die Meldungen der ersten drei Faelle sind deutsch und fuer die Oberflaeche
 * gedacht: sie landen im Verkaeufer-Portal direkt vor dem Verkaeufer.
 */
class PayoutNotAllowedException extends RuntimeException
{
    /**
     * Ohne IBAN gibt es kein Ziel fuer die Ueberweisung.
     */
    public static function missingIban(): self
    {
        return new self(__('marketplace.wallet.payout.errors.missing_iban'));
    }

    /**
     * Unter dem Mindestbetrag: Kleinbetraege lohnen die manuelle Ueberweisung
     * nicht.
     */
    public static function belowMinimum(int $amountCents, int $minimumCents): self
    {
        return new self(__('marketplace.wallet.payout.errors.below_minimum', [
            'amount' => self::formatAmount($amountCents),
            'minimum' => self::formatAmount($minimumCents),
        ]));
    }

    /**
     * Mehr angefordert als im Topf liegt.
     */
    public static function exceedsBalance(int $amountCents, int $availableCents): self
    {
        return new self(__('marketplace.wallet.payout.errors.exceeds_balance', [
            'amount' => self::formatAmount($amountCents),
            'available' => self::formatAmount($availableCents),
        ]));
    }

    /**
     * Ueber diese Anforderung ist schon entschieden.
     */
    public static function alreadyProcessed(PayoutRequest $request, PayoutStatus $target): self
    {
        return new self(sprintf(
            'Die Auszahlungsanforderung #%s steht auf "%s"; "%s" ist nur aus "%s" heraus moeglich.',
            (string) $request->getKey(),
            $request->status->value,
            $target->value,
            PayoutStatus::REQUESTED->value,
        ));
    }

    private static function formatAmount(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.config('wallet.currency');
    }
}
