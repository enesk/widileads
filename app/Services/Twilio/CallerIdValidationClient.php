<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Dto\CallerIdValidation;

/**
 * Zugang zur Twilio-API fuer Outgoing Caller IDs (FB-080).
 *
 * Als Schnittstelle, damit der Anrufweg pruefbar bleibt, ohne bei jedem Test
 * ein Telefon klingeln zu lassen.
 */
interface CallerIdValidationClient
{
    /**
     * Fordert die Bestaetigung an. Twilio ruft die Nummer daraufhin an und sagt
     * den Code an; das Ergebnis meldet Twilio spaeter an $statusCallbackUrl.
     *
     * @throws CallerIdValidationFailed wenn Twilio die Anforderung ablehnt.
     */
    public function requestValidation(
        string $phoneNumber,
        string $friendlyName,
        string $statusCallbackUrl,
    ): CallerIdValidation;
}
