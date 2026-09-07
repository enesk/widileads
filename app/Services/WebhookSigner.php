<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Signiert ausgehende Ereignisse (FB-030e).
 *
 * Signiert wird ueber Zeitstempel UND Rumpf: Ohne den Zeitstempel im Hash
 * koennte jemand eine mitgeschnittene Zustellung beliebig oft wiedereinspielen,
 * und die Signatur waere jedes Mal gueltig. Der Empfaenger prueft deshalb
 * beides -- Signatur und Alter.
 *
 * Verglichen wird mit hash_equals: Ein zeichenweiser Vergleich verraet ueber
 * seine Laufzeit, wie viele Zeichen stimmen.
 */
class WebhookSigner
{
    public const SIGNATURE_HEADER = 'X-Funnel-Signature';

    public const TIMESTAMP_HEADER = 'X-Funnel-Timestamp';

    public const EVENT_HEADER = 'X-Funnel-Event';

    public function sign(string $secret, string $payload, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    public function verify(string $secret, string $payload, int $timestamp, string $signature): bool
    {
        return hash_equals($this->sign($secret, $payload, $timestamp), $signature);
    }

    /**
     * @return array<string, string>
     */
    public function headersFor(string $secret, string $payload, string $event, int $timestamp): array
    {
        return [
            self::SIGNATURE_HEADER => $this->sign($secret, $payload, $timestamp),
            self::TIMESTAMP_HEADER => (string) $timestamp,
            self::EVENT_HEADER => $event,
        ];
    }
}
