<?php

declare(strict_types=1);

namespace App\Services\Twilio;

/**
 * Startet ausgehende Anrufe bei Twilio (FB-081).
 *
 * Als Schnittstelle, damit der Anrufweg pruefbar bleibt, ohne dass in einem
 * Test ein Telefon klingelt.
 */
interface OutboundCallClient
{
    /**
     * Ruft $to von $from aus an. Nimmt jemand ab, holt Twilio die Anweisung,
     * was weiter geschehen soll, per POST von $answerUrl; jede Standaenderung
     * meldet es an $statusCallbackUrl.
     *
     * @return string Kennung des Anrufs bei Twilio.
     *
     * @throws OutboundCallFailed
     */
    public function call(string $to, string $from, string $answerUrl, string $statusCallbackUrl): string;

    /**
     * Legt den Anruf mit der Kennung $sid auf. Wird gebraucht, wenn beim Lead
     * ein Anrufbeantworter abgenommen hat (FB-081).
     *
     * @throws OutboundCallFailed
     */
    public function hangUp(string $sid): void;
}
