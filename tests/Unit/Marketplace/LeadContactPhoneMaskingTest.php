<?php

declare(strict_types=1);

namespace Tests\Unit\Marketplace;

use App\Dto\LeadContact;
use App\Models\Lead;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FB-032: Die verdeckte Rufnummer vor dem Kauf.
 *
 * Diese Fassung sieht jeder Kaeufer im Marktplatz, also auch der, der den Lead
 * nie kauft. Geprueft werden deshalb die Grenzfaelle, in denen libphonenumber
 * die Nummer nicht in Landesvorwahl, Netzkennzahl und Anschluss zerlegt --
 * dort stand das vermeintliche Praefix frueher fuer die ganze Nummer.
 */
class LeadContactPhoneMaskingTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function phoneProvider(): array
    {
        return [
            'in Gruppen zerlegbar' => ['+493012345678', '+49 30 …'],
            'national notiert' => ['0151 23456789', '+49 1512 …'],
            'ohne Netzkennzahl gruppiert' => ['+491503000006', '+49 …'],
            'nicht parsbar' => ['0151 23456789 Durchwahl', '…'],
            'unbekannte Landesvorwahl' => ['+99 000', '…'],
        ];
    }

    #[DataProvider('phoneProvider')]
    public function test_the_masked_phone_never_shows_the_whole_number(string $phone, string $expected): void
    {
        $this->assertSame($expected, $this->maskedPhone($phone));
    }

    public function test_a_missing_phone_stays_missing(): void
    {
        $this->assertNull($this->maskedPhone(null));
    }

    private function maskedPhone(?string $phone): ?string
    {
        $lead = new Lead;
        $lead->setRelation('answers', new Collection);
        $lead->phone_e164 = $phone;

        return LeadContact::fromLead($lead)->masked()->phone;
    }
}
