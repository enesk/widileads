<?php

declare(strict_types=1);

namespace App\Constants;

use Illuminate\Support\Str;

/**
 * Reservierte Feldschluessel eines Funnels (FB-010).
 *
 * Diese Schluessel haben plattformweit eine feste Bedeutung: Sie tragen die
 * Kontaktdaten eines Leads und werden spaeter serverseitig maskiert. Ein Funnel
 * darf sie verwenden, aber nicht mit einer anderen Bedeutung belegen.
 *
 * Achtung: Normalisierung und Reservierung sind zwei Dinge. Das Label "E-Mail"
 * ergibt den Schluessel "e_mail" und ist damit ein freier Schluessel -- reserviert
 * ist nur "email". Beide Vorgaben stammen unveraendert aus FB-010; siehe
 * docs/BACKLOG.md.
 */
enum FunnelFieldKey: string
{
    case VORNAME = 'vorname';

    case NACHNAME = 'nachname';

    case NAME = 'name';

    case EMAIL = 'email';

    case TELEFON = 'telefon';

    case PLZ = 'plz';

    case EINWILLIGUNG = 'einwilligung';

    /**
     * Normalisiert eine Eingabe zu einem Feldschluessel: Kleinschreibung,
     * Umlaute ausgeschrieben, Trennzeichen zu Unterstrichen ("E-Mail" -> "e_mail").
     */
    public static function normalize(string $fieldKey): string
    {
        return Str::slug(trim($fieldKey), '_');
    }

    /**
     * Ist der Schluessel plattformweit reserviert?
     */
    public static function isReserved(string $fieldKey): bool
    {
        return self::tryFrom(self::normalize($fieldKey)) !== null;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
