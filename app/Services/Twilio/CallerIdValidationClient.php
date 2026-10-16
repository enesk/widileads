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

    /**
     * Nimmt eine bestaetigte Nummer im Twilio-Konto zurueck (Ticket #1).
     *
     * Gesucht wird ueber die Nummer, nicht ueber eine gemerkte Kennung: Eine
     * Validation Request liefert nur die SID des Bestaetigungsanrufs
     * (`caller_ids.validation_sid`), der daraus entstandene Eintrag unter
     * Verified Caller IDs hat eine eigene, die wir nie zu sehen bekommen.
     *
     * @return int Anzahl der entfernten Eintraege. 0 heisst: Es stand dort
     *             nichts mehr -- das ist kein Fehler.
     *
     * @throws CallerIdValidationFailed wenn Twilio die Anfrage ablehnt.
     */
    public function deleteValidatedNumber(string $phoneNumber): int;
}
