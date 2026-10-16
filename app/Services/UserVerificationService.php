<?php

namespace App\Services;

use App\Services\VerificationProviders\VerificationProviderInterface;
use Exception;

/**
 * Zugriff auf die SMS-Anbieter von SaaSykit (LP-ONBOARD-023).
 *
 * Uebrig ist nur noch das Nachschlagen eines Anbieters -- daran haengt die
 * Test-SMS im Admin. Die eigene Rufnummernbestaetigung dieser Klasse ist
 * abgebaut: Sie erzeugte den Code mit `mt_rand`, hielt ihn in der Session und
 * setzte `users.phone_number_verified_at` ohne Rate-Limit, ohne E.164 und ohne
 * Pruefung, ob die Nummer schon einem anderen Konto gehoert. Damit war jede
 * Zusicherung des gewollten Wegs umgehbar.
 *
 * Rufnummern bestaetigt ausschliesslich App\Services\CallerIdService, und
 * zwar ueber den Bestaetigungsanruf von Twilio (Ticket #97).
 */
class UserVerificationService
{
    private array $verificationProviders = [];

    public function setVerificationProviders(VerificationProviderInterface ...$verificationProviders): void
    {
        $this->verificationProviders = $verificationProviders;
    }

    public function getProviderBySlug(string $providerSlug): VerificationProviderInterface
    {
        foreach ($this->verificationProviders as $provider) {
            if ($provider->getSlug() === $providerSlug) {
                return $provider;
            }
        }

        throw new Exception('No verification provider found with slug '.$providerSlug);
    }
}
