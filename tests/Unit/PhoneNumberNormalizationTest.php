<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Funnel\QuestionTypes\PhoneType;
use App\Models\FunnelQuestion;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FB-011: Telefonnummern werden nach E.164 normalisiert.
 *
 * Das ist die einzige Umwandlung im Ticket, an der spaeter echtes Geld haengt:
 * Nur in E.164 sind Nummern vergleichbar (Dublettenpruefung) und waehlbar
 * (Anrufnachweis, Phase 2). Deshalb hier abgesichert, nicht bloss angenommen.
 */
class PhoneNumberNormalizationTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function germanNumberProvider(): array
    {
        return [
            'nationale Schreibweise' => ['0151 12345678', '+4915112345678'],
            'mit Klammern und Bindestrich' => ['(0151) 1234-5678', '+4915112345678'],
            'bereits E.164' => ['+4915112345678', '+4915112345678'],
            'mit Landesvorwahl als 00' => ['004915112345678', '+4915112345678'],
            'Festnetz mit Leerzeichen' => ['030 123456', '+4930123456'],
        ];
    }

    #[DataProvider('germanNumberProvider')]
    public function test_phone_numbers_are_normalized_to_e164(string $input, string $expected): void
    {
        $question = new FunnelQuestion(['field_key' => 'telefon']);

        $this->assertSame($expected, app(PhoneType::class)->normalize($input, $question));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNumberProvider(): array
    {
        return [
            'Buchstaben' => ['keine Nummer'],
            'zu kurz' => ['123'],
            'leer nach dem Trimmen' => ['   '],
            'nur Sonderzeichen' => ['+++'],
        ];
    }

    #[DataProvider('invalidNumberProvider')]
    public function test_unusable_input_is_not_turned_into_a_phone_number(string $input): void
    {
        $phoneType = app(PhoneType::class);

        $this->assertNull($phoneType->toE164($input));

        // Die Eingabe geht nicht verloren: Was sich nicht lesen laesst, bleibt
        // unveraendert stehen und faellt in der Validierung auf.
        $normalized = $phoneType->normalize($input, new FunnelQuestion(['field_key' => 'telefon']));

        $this->assertTrue($normalized === null || $normalized === trim($input));
    }

    public function test_the_region_for_numbers_without_country_code_comes_from_the_config(): void
    {
        $phoneType = app(PhoneType::class);

        config()->set('funnel.question.default_phone_region', 'AT');

        $this->assertSame('+436601234567', $phoneType->toE164('0660 1234567'));
        $this->assertSame('+4915112345678', $phoneType->toE164('0151 12345678', 'DE'));
    }
}
