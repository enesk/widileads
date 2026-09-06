<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Gesalzener SHA-256-Hash einer IP-Adresse (FB-005, seit FB-022 hier zentral).
 *
 * Die Roh-IP wird nirgends gespeichert und nirgends geloggt -- sie verlaesst
 * diesen Dienst nur als Hash. Gleiche IP und gleicher Salt ergeben denselben
 * Hash, sodass Herkunft und Haeufung vergleichbar bleiben (Rate-Limit,
 * Dublettenpruefung), ohne dass jemand die Adresse zurueckrechnen kann.
 *
 * Es gibt bewusst genau ein Verfahren an genau einer Stelle: Zwei
 * Hash-Implementierungen wuerden zu zwei unterschiedlichen Hashes derselben
 * Adresse fuehren, und damit waere jeder Vergleich wertlos.
 */
class IpHasher
{
    public function hash(?string $ipAddress): ?string
    {
        if ($ipAddress === null || trim($ipAddress) === '') {
            return null;
        }

        return hash('sha256', $this->salt().'|'.$ipAddress);
    }

    /**
     * Ohne eigenen Salt dient APP_KEY als Salt. Ein Wechsel des Salts macht
     * alte Hashes unvergleichbar -- gewollt, etwa nach einem Leak.
     */
    private function salt(): string
    {
        $salt = config('funnel.audit.ip_salt');

        if (is_string($salt) && $salt !== '') {
            return $salt;
        }

        return (string) config('app.key');
    }
}
