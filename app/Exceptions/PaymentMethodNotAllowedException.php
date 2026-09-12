<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\PaymentMethod;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ein Zahlungsmittel laesst sich so nicht hinterlegen oder entfernen
 * (LP-POSTPAID-005).
 *
 * Der wichtigste Fall ist das letzte Mittel eines Postpaid-Wallets: Wer im
 * Nachzahlungsverfahren kauft, hat der Plattform einen Einzugsweg zugesagt.
 * Faellt der weg, waehrend noch ein Betrag offen ist, bleibt eine Forderung
 * ohne Weg zum Geld. Deshalb 422 mit Hinweis statt stiller Loeschung -- der
 * Kaeufer soll zuerst ein anderes Mittel hinterlegen oder seinen offenen
 * Betrag begleichen.
 *
 * Die uebrigen Faelle betreffen den Ablauf beim Anbieter: ein SetupIntent, der
 * nicht zu diesem Wallet gehoert oder nicht erfolgreich abgeschlossen ist, und
 * ein SEPA-Mandat ohne Einwilligung des Kaeufers.
 *
 * Die Meldungen sind deutsch und fuer die Oberflaeche gedacht: sie landen im
 * Portal direkt vor dem Kaeufer.
 */
class PaymentMethodNotAllowedException extends RuntimeException
{
    /**
     * Das letzte einsatzbereite Mittel eines Postpaid-Wallets darf nicht weg,
     * solange das Verfahren laeuft oder ein Betrag offen ist.
     */
    public static function lastMethodOfPostpaidWallet(int $openAmountCents): self
    {
        $key = $openAmountCents > 0
            ? 'marketplace.wallet.payment_methods.errors.last_method_open_amount'
            : 'marketplace.wallet.payment_methods.errors.last_method_postpaid';

        return new self(__($key, [
            'amount' => self::formatAmount($openAmountCents),
        ]), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Der SetupIntent steht beim Anbieter nicht auf `succeeded`. Das ist kein
     * Fehler des Kaeufers: Bank oder Karte haben abgelehnt, oder er hat den
     * Vorgang abgebrochen.
     */
    public static function setupNotCompleted(string $status): self
    {
        return new self(__('marketplace.wallet.payment_methods.errors.setup_not_completed', [
            'status' => $status,
        ]), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Der SetupIntent gehoert zu einem anderen Wallet. Ein Fremdzugriff, kein
     * Bedienfehler -- deshalb ohne Hinweis darauf, wem er gehoert.
     */
    public static function setupIntentMismatch(): self
    {
        return new self(
            __('marketplace.wallet.payment_methods.errors.setup_intent_unknown'),
            Response::HTTP_NOT_FOUND,
        );
    }

    /**
     * Ohne angehakte Mandatseinwilligung gibt es kein Lastschriftmandat und
     * damit keinen Einzug.
     */
    public static function mandateNotAccepted(): self
    {
        return new self(
            __('marketplace.wallet.payment_methods.errors.mandate_required'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Stripe hat zu einem SEPA-Zahlungsmittel kein Mandat geliefert. Ohne
     * Mandatskennung ist eine spaetere Lastschrift nicht belegbar, also wird
     * das Mittel nicht gespeichert.
     */
    public static function mandateMissing(): self
    {
        return new self(
            __('marketplace.wallet.payment_methods.errors.mandate_missing'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Zahlungsmittel gehoeren an das Kauf-Wallet. Ein Verkaufs- oder
     * Plattform-Wallet zieht nichts ein -- das ist ein Fehler im Aufrufer.
     */
    public static function notABuyerWallet(): self
    {
        return new self('Zahlungsmittel koennen nur an einem Kauf-Wallet hinterlegt werden.');
    }

    /**
     * Dieses Mittel gehoert nicht zum angegebenen Wallet.
     */
    public static function foreignMethod(PaymentMethod $method): self
    {
        return new self(sprintf(
            'Zahlungsmittel #%s gehoert zu Wallet #%s und nicht zum angegebenen.',
            (string) $method->getKey(),
            (string) $method->wallet_id,
        ), Response::HTTP_NOT_FOUND);
    }

    private static function formatAmount(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.config('wallet.currency');
    }
}
