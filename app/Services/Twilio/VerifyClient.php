<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use App\Constants\PhoneVerificationChannel;

/**
 * Zugang zu Twilio Verify (LP-ONBOARD-004).
 *
 * Als Schnittstelle, damit die Attrappe (FakeVerifyClient) dieselbe Stelle
 * besetzt wie die echte Anbindung -- lokal, auf Staging und in Tests laeuft
 * dann derselbe Dienst, nur ohne SMS.
 *
 * Die Codes erzeugt und prueft Twilio. Diese Schnittstelle hat deshalb
 * absichtlich keine Methode, die einen Code herausgibt.
 */
interface VerifyClient
{
    /**
     * Laesst Twilio einen Code an die Nummer schicken.
     *
     * @return string Die Kennung des Vorgangs bei Twilio ("VE...").
     *
     * @throws VerifyRequestFailed wenn Twilio die Anforderung ablehnt.
     */
    public function startVerification(string $phoneE164, PhoneVerificationChannel $channel): string;

    /**
     * Prueft den eingegebenen Code gegen den offenen Vorgang der Nummer.
     *
     * False heisst: falsch, abgelaufen oder zu oft versucht. Twilio
     * unterscheidet das nicht nach aussen, und wir wollen es dem Nutzer auch
     * nicht sagen -- jede Unterscheidung waere ein Hinweis fuer das Raten.
     *
     * @throws VerifyRequestFailed wenn die Pruefung nicht stattfinden konnte.
     */
    public function checkVerification(string $phoneE164, string $code): bool;
}
