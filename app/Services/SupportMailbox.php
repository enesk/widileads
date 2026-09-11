<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Die Betreiberadresse, an die das Portal seine internen Meldungen schickt
 * (Ticket #25).
 *
 * Gelesen wird `app.support_email` -- gepflegt ueber die Admin-Seite
 * "Allgemeine Einstellungen" (Tabelle `configs`), sonst aus SUPPORT_EMAIL.
 * Frueher stand dort fest `support@saasykit.com`: In einer Umgebung ohne
 * gepflegten Eintrag waeren Auszahlungsanforderungen, Saldenabweichungen und
 * fehlgeschlagene Abrechnungen an eine fremde Domain gegangen.
 *
 * Deshalb gilt eine Adresse auf einer Starterkit-Domain hier als NICHT
 * gesetzt: Lieber keine Meldung und ein Protokolleintrag als interne
 * Geldvorgaenge in einem fremden Postfach.
 */
class SupportMailbox
{
    /**
     * Domains des Starterkits. Eine Adresse darauf ist nie die des Betreibers.
     *
     * @var list<string>
     */
    private const STARTER_KIT_DOMAINS = ['saasykit.com', 'example.com'];

    /**
     * Verwendbare Betreiberadresse oder null.
     */
    public function address(): ?string
    {
        $address = trim((string) config('app.support_email'));

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        if (self::isStarterKitAddress($address)) {
            return null;
        }

        return $address;
    }

    /**
     * Adresse fuer eine Meldung, die sonst still verloren ginge -- ist keine
     * brauchbare hinterlegt, steht der Grund im Protokoll.
     *
     * @param  array<string, mixed>  $context
     */
    public function addressOrLog(string $reason, array $context = []): ?string
    {
        $address = $this->address();

        if ($address === null) {
            Log::warning($reason, $context + ['support_email' => (string) config('app.support_email')]);
        }

        return $address;
    }

    /**
     * Traegt die Adresse eine Domain des Starterkits?
     */
    public static function isStarterKitAddress(string $address): bool
    {
        $domain = strtolower((string) substr(strrchr($address, '@') ?: '', 1));

        return in_array($domain, self::STARTER_KIT_DOMAINS, true);
    }
}
