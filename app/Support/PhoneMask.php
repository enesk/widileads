<?php

declare(strict_types=1);

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Rufnummern fuer die Anzeige verkuerzen (LP-ONBOARD-005).
 *
 * Gebraucht auf dem Code-Screen der Rufnummernbestaetigung: Dort steht, an
 * welche Nummer der Code ging -- "+49 172 ••• ••67". Vollstaendig muss sie
 * dort nicht stehen. Wer den Screen offen hat, kennt seine Nummer; die letzten
 * beiden Ziffern genuegen zum Wiedererkennen, und ein Blick ueber die Schulter
 * oder ein Bildschirmfoto im Support-Ticket traegt die Nummer dann nicht
 * weiter.
 *
 * **Nicht derselbe Fall wie App\Dto\LeadContact::maskPhoneToLastDigits().**
 * Dort verdeckt die Maske eine Nummer, die der Betrachter nicht kennen DARF
 * (FB-085) -- sie ist eine Zugriffsgrenze und benutzt deshalb Sternchen, die
 * man in ein Formular tippen koennte. Hier geht es um die eigene Nummer des
 * Nutzers, und die Maske ist reine Anzeige. Beides in einer Funktion
 * zusammenzuziehen hiesse, eine Zugriffsgrenze an einem Darstellungswunsch
 * mitzuaendern.
 *
 * Die Landesvorwahl und die Netzkennzahl bleiben stehen: An ihnen erkennt der
 * Nutzer, ob er die Mobilnummer oder die der Werkstatt eingetippt hat.
 */
class PhoneMask
{
    /**
     * Das Zeichen der Maske. Ein Punkt und kein Sternchen -- ein Sternchen
     * sieht aus wie ein Zeichen, das man selbst eingeben koennte.
     */
    private const DOT = '•';

    /**
     * Die Nummer mit verdecktem Teilnehmeranschluss: "+49 172 ••• ••67".
     *
     * Erwartet E.164, nimmt aber jede lesbare Schreibweise. Was libphonenumber
     * nicht zerlegen kann, wird bis auf die letzten beiden Ziffern verdeckt --
     * im Zweifel weniger zeigen.
     */
    public static function partial(?string $phone, string $region = 'DE'): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $groups = self::internationalGroups($phone, $region);

        if ($groups === null || count($groups) < 3) {
            return self::maskAllButLastDigits($phone);
        }

        // Landesvorwahl und Netzkennzahl bleiben, der Rest ist der
        // Teilnehmeranschluss.
        $visiblePrefix = implode(' ', array_slice($groups, 0, 2));
        $subscriber = preg_replace('/\D/', '', implode('', array_slice($groups, 2))) ?? '';

        if (mb_strlen($subscriber) < 3) {
            // Zu kurz, um etwas zu verdecken und gleichzeitig etwas zu zeigen.
            return self::maskAllButLastDigits($phone);
        }

        $masked = str_repeat(self::DOT, mb_strlen($subscriber) - 2).mb_substr($subscriber, -2);

        return $visiblePrefix.' '.self::group($masked);
    }

    /**
     * Die Nummer in internationaler Schreibweise, in ihre Gruppen zerlegt --
     * oder null, wenn sie nicht lesbar ist.
     *
     * @return list<string>|null
     */
    private static function internationalGroups(string $phone, string $region): ?array
    {
        try {
            $parsed = PhoneNumberUtil::getInstance()->parse(trim($phone), $region);
        } catch (NumberParseException) {
            return null;
        }

        $international = PhoneNumberUtil::getInstance()->format($parsed, PhoneNumberFormat::INTERNATIONAL);

        return array_values(array_filter(explode(' ', $international), fn (string $group): bool => $group !== ''));
    }

    /**
     * Dreierbloecke von links, ein einzelnes Zeichen am Ende wandert in den
     * Block davor: aus "•••••67" wird "••• ••67".
     *
     * Ohne das Zusammenziehen stuende dort "••• ••6 7" -- eine Gruppe aus
     * einer Ziffer liest man als Tippfehler.
     */
    private static function group(string $masked): string
    {
        $chunks = mb_str_split($masked, 3);
        $last = count($chunks) - 1;

        if ($last > 0 && mb_strlen($chunks[$last]) === 1) {
            $chunks[$last - 1] .= $chunks[$last];
            array_pop($chunks);
        }

        return implode(' ', $chunks);
    }

    /**
     * Der Notfall: alles bis auf die letzten beiden Ziffern verdecken.
     */
    private static function maskAllButLastDigits(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return str_repeat(self::DOT, 4);
        }

        return str_repeat(self::DOT, max(0, mb_strlen($digits) - 2)).mb_substr($digits, -2);
    }
}
