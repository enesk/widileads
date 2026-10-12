<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ein Limit der Rufnummernbestaetigung ist erreicht (LP-ONBOARD-004).
 *
 * Drei Faelle, eine Ausnahme: zu viele Code-Anforderungen fuer eine Nummer, zu
 * viele Pruefungen in einer Sitzung, zu frueh erneut gesendet. Alle drei
 * schuetzen denselben Punkt -- jede Anforderung kostet Geld und klingelt bei
 * einem Menschen, und jede Pruefung ist ein Rateversuch.
 *
 * Die Wartezeit gehoert zur Ausnahme: Ein "versuch es spaeter" ohne Zeitangabe
 * laesst den Nutzer im Sekundentakt weiterdruecken.
 */
class PhoneVerificationThrottledException extends RuntimeException
{
    private function __construct(string $message, public readonly int $retryAfterSeconds)
    {
        parent::__construct($message, Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * Zu viele Code-Anforderungen fuer diese Nummer im Zeitfenster.
     */
    public static function tooManyRequests(int $retryAfterSeconds): self
    {
        return new self(__('portal.phone_verification.errors.too_many_requests', [
            'wait' => self::humanWait($retryAfterSeconds),
        ]), $retryAfterSeconds);
    }

    /**
     * Zu viele Code-Pruefungen in dieser Sitzung.
     */
    public static function tooManyChecks(int $retryAfterSeconds): self
    {
        return new self(__('portal.phone_verification.errors.too_many_checks', [
            'wait' => self::humanWait($retryAfterSeconds),
        ]), $retryAfterSeconds);
    }

    /**
     * Der Knopf "Code erneut senden" wurde zu frueh gedrueckt.
     */
    public static function resendTooEarly(int $retryAfterSeconds): self
    {
        return new self(__('portal.phone_verification.errors.resend_too_early', [
            'seconds' => $retryAfterSeconds,
        ]), $retryAfterSeconds);
    }

    /**
     * Der Ausweg per Anruf wurde verlangt, ohne dass je eine SMS versucht
     * wurde. Keine Wartezeit -- es fehlt eine Handlung, keine Zeit.
     */
    public static function callRequiresSmsFirst(): self
    {
        return new self(__('portal.phone_verification.errors.call_requires_sms'), 0);
    }

    /**
     * Der HTTP-Status: 429, wie im Ticket verlangt.
     */
    public function status(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }

    /**
     * Ab wann es wieder geht -- fuer den Zaehler in der Oberflaeche.
     */
    public function retryAt(): Carbon
    {
        return now()->addSeconds($this->retryAfterSeconds);
    }

    /**
     * Sekunden bis etwa eine Minute, darueber Minuten. "Noch 3540 Sekunden"
     * liest niemand.
     */
    private static function humanWait(int $seconds): string
    {
        if ($seconds < 90) {
            return trans_choice('portal.phone_verification.wait.seconds', $seconds, ['count' => $seconds]);
        }

        $minutes = (int) ceil($seconds / 60);

        return trans_choice('portal.phone_verification.wait.minutes', $minutes, ['count' => $minutes]);
    }
}
