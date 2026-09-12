<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\EligibilityResult;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ein Schritt des Freischaltungsverfahrens ist so nicht zulaessig
 * (LP-POSTPAID-006).
 *
 * Die Meldungen sind deutsch und fuer die Oberflaeche gedacht -- sie landen im
 * Portal vor dem Kaeufer oder im Admin vor Enes. Der Statuscode steckt an der
 * Ausnahme selbst, damit ein spaeterer Endpunkt ihn nicht neu erfinden muss:
 * 403 fuer das abgeschaltete Verfahren (der Kaeufer kann daran nichts aendern),
 * 422 fuer alles, was an seinem Zustand liegt.
 */
class PostpaidNotAllowedException extends RuntimeException
{
    /**
     * Der Hauptschalter steht auf false. Kein Antrag, egal wie geeignet der
     * Kaeufer waere.
     */
    public static function featureDisabled(): self
    {
        return new self(
            __('marketplace.wallet.postpaid.errors.disabled'),
            Response::HTTP_FORBIDDEN,
        );
    }

    /**
     * Der Kaeufer erfuellt die Voraussetzungen nicht. Die Begruendungen der
     * Pruefung stehen in der Meldung -- sie sind fuer ihn geschrieben.
     */
    public static function notEligible(EligibilityResult $result): self
    {
        return new self(
            trim(__('marketplace.wallet.postpaid.errors.not_eligible').' '.$result->reasonText()),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Ohne einsatzbereites Standard-Zahlungsmittel gibt es keinen Weg zum
     * Geld. Ein Kreditrahmen ohne Einzugsweg waere eine Forderung auf gut
     * Glueck.
     */
    public static function paymentMethodRequired(): self
    {
        return new self(
            __('marketplace.wallet.postpaid.errors.payment_method_required'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Es liegt bereits ein unentschiedener Antrag vor. Ein zweiter wuerde den
     * ersten nicht beschleunigen, aber die Arbeitsliste des Admins verdoppeln.
     */
    public static function applicationPending(): self
    {
        return new self(
            __('marketplace.wallet.postpaid.errors.application_pending'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Nach einer Ablehnung gilt eine Sperrfrist
     * (config('wallet.postpaid.reapply_after_days')).
     */
    public static function rejectedRecently(int $daysLeft): self
    {
        return new self(__('marketplace.wallet.postpaid.errors.rejected_recently', [
            'days' => (string) $daysLeft,
        ]), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Der Kaeufer kauft bereits mit Pay as you go.
     */
    public static function alreadyEnabled(): self
    {
        return new self(
            __('marketplace.wallet.postpaid.errors.already_enabled'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Ueber diesen Antrag ist schon entschieden. Das ist ein Fehler im
     * Aufrufer und keine Meldung fuer den Kaeufer.
     */
    public static function alreadyDecided(int $applicationId, string $status): self
    {
        return new self(sprintf(
            'Ueber Antrag #%d ist bereits entschieden (Stand: %s).',
            $applicationId,
            $status,
        ), Response::HTTP_CONFLICT);
    }

    /**
     * Kreditrahmen gibt es nur am Kauf-Wallet eines Postpaid-Kaeufers.
     */
    public static function notPostpaid(): self
    {
        return new self(
            'Ein Kreditrahmen laesst sich nur an einem freigeschalteten Postpaid-Wallet aendern.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Zahlungsmodus und Rahmen gehoeren an das Kauf-Wallet. Ein Verkaufs- oder
     * Plattform-Wallet kauft nichts -- das ist ein Fehler im Aufrufer.
     */
    public static function notABuyerWallet(): self
    {
        return new self('Pay as you go gibt es nur am Kauf-Wallet eines Mandanten.');
    }

    /**
     * Ein negativer Rahmen waere eine Vorauszahlungspflicht und kein Kredit.
     */
    public static function invalidCreditLimit(int $cents): self
    {
        return new self(sprintf('Der Kreditrahmen darf nicht negativ sein, angegeben waren %d Cent.', $cents));
    }

    /**
     * Statuscode fuer einen Endpunkt, der diese Ausnahme abfaengt.
     */
    public function status(): int
    {
        $code = $this->getCode();

        return $code >= 400 && $code <= 599 ? (int) $code : Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
