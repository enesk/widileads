<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\FeatureTest;

/**
 * Ticket #101: Die Absenderpruefung von `leads:check-call-setup` meldete
 * "hello@example.com" monatelang als OK, weil sie nur auf "nicht leer" sah.
 *
 * Geprueft wird deshalb genau diese Zeile: Ein Platzhalter muss FEHLER sein
 * und den Lauf scheitern lassen -- eine Pruefung, die bei einer Adresse OK
 * sagt, von der keine Mail durchkommt, ist schlechter als keine Pruefung.
 */
class CheckCallSetupTest extends FeatureTest
{
    /**
     * @return list<array{0: string}>
     */
    public static function brokenAddresses(): array
    {
        return [
            ['widimedia'],
            ['root@localhost'],
            ['noreply@mailpit'],
        ];
    }

    /**
     * @return list<array{0: string}>
     */
    public static function placeholderAddresses(): array
    {
        return [
            ['hello@example.com'],
            ['support@saasykit.com'],
            ['info@example.org'],
            ['noreply@widileads.test'],
            ['kontakt@widileads.local'],
        ];
    }

    #[DataProvider('placeholderAddresses')]
    public function test_it_reports_a_placeholder_sender_address_as_a_failure(string $address): void
    {
        config()->set('mail.from.address', $address);

        $this->artisan('leads:check-call-setup')
            ->expectsOutputToContain('Platzhalter')
            ->assertFailed();
    }

    /**
     * Ohne Punkt in der Domain (`root@localhost`, `info@mailpit`) scheitert
     * schon die Adresspruefung -- der Befund ist dann nicht "Platzhalter",
     * aber immer noch FEHLER.
     */
    #[DataProvider('brokenAddresses')]
    public function test_it_reports_a_sender_address_that_is_no_address_as_a_failure(string $address): void
    {
        config()->set('mail.from.address', $address);

        $this->artisan('leads:check-call-setup')
            ->expectsOutputToContain('keine Adresse')
            ->assertFailed();
    }

    public function test_a_real_sender_address_passes_that_check(): void
    {
        config()->set('mail.from.address', 'info@widimedia.com');

        // Der ganze Lauf scheitert in der Testumgebung an anderen Punkten
        // (Twilio-Zugangsdaten, Portal-Nummer) -- die Absenderzeile darf
        // darunter nicht sein.
        $this->artisan('leads:check-call-setup')
            ->doesntExpectOutputToContain('Platzhalter')
            ->doesntExpectOutputToContain('mail.from.address');
    }
}
