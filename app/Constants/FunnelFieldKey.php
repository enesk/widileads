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
 * Ein Feldschluessel entsteht in zwei Schritten: normalize() vereinheitlicht die
 * Schreibweise (Label "E-Mail" ergibt "e_mail"), resolve() bildet das Ergebnis
 * anschliessend ueber config('funnel.field_key_aliases') auf den reservierten
 * Schluessel ab ("e_mail" wird zu "email"). Ohne den zweiten Schritt entstuende
 * ein Lead, dessen Kontaktdaten spaeter niemand findet.
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
     * Erster Schritt: vereinheitlicht die Schreibweise -- Kleinschreibung,
     * Umlaute ausgeschrieben, Trennzeichen zu Unterstrichen ("E-Mail" -> "e_mail").
     */
    public static function normalize(string $fieldKey): string
    {
        return Str::slug(trim($fieldKey), '_');
    }

    /**
     * Zweiter Schritt: normalisiert und loest gebraeuchliche Schreibweisen auf
     * den reservierten Feldschluessel auf ("E-Mail" -> "email", "Handy" ->
     * "telefon"). Ist kein Alias hinterlegt, bleibt der normalisierte Schluessel
     * unveraendert.
     */
    public static function resolve(string $fieldKey): string
    {
        $normalized = self::normalize($fieldKey);
        $alias = self::aliases()[$normalized] ?? null;

        if ($alias === null || self::tryFrom($alias) === null) {
            return $normalized;
        }

        return $alias;
    }

    /**
     * Ist der Schluessel -- nach Aufloesung der Aliase -- plattformweit reserviert?
     */
    public static function isReserved(string $fieldKey): bool
    {
        return self::tryFrom(self::resolve($fieldKey)) !== null;
    }

    /**
     * Hinterlegte Aliase, Schreibweise -> reservierter Feldschluessel.
     *
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        /** @var array<string, string> $aliases */
        $aliases = config('funnel.field_key_aliases', []);

        return $aliases;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
