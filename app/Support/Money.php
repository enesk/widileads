<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Geldbetraege des Wallets fuer die Anzeige (LP-WALLET-011).
 *
 * Gerechnet wird im ganzen Marktplatz in Cent; formatiert wird ausschliesslich
 * hier. Vorher stand `number_format($cents / 100, 2, ',', '.')` in jeder
 * zweiten Ansicht -- eine davon vergisst frueher oder spaeter das Eurozeichen
 * oder rundet anders als der Beleg.
 *
 * Abweichung von der Ticketvorgabe "Helper money($cents)": Eine globale
 * Funktion `money()` gibt es bereits, sie stammt aus saasykit/laravel-money und
 * nimmt Betrag *und* Waehrung entgegen. Sie zu ueberschreiben waere ein
 * Fehler mit Ansage, deshalb eine eigene, ausdruecklich benannte Klasse.
 */
final class Money
{
    /**
     * Cent als Betrag in deutscher Schreibweise: "1.234,50 €".
     */
    public static function format(int $cents, ?string $currency = null): string
    {
        return number_format($cents / 100, 2, ',', '.').' '.self::symbol($currency);
    }

    /**
     * Wie format(), aber mit ausdruecklichem Vorzeichen: "+50,00 €".
     *
     * Im Journal ist das Vorzeichen die halbe Aussage -- ohne das Plus liest
     * sich eine Gutschrift wie eine Abbuchung ohne Minus.
     */
    public static function formatSigned(int $cents, ?string $currency = null): string
    {
        return ($cents > 0 ? '+' : '').self::format($cents, $currency);
    }

    /**
     * Nur die Zahl, ohne Waehrung -- fuer Eingabefelder und CSV.
     */
    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    /**
     * Waehrungszeichen der Wallet-Waehrung. Unbekannte Waehrungen werden mit
     * ihrem Code geschrieben; erfundene Zeichen waeren schlechter als ein Code.
     */
    private static function symbol(?string $currency = null): string
    {
        $code = strtoupper($currency ?? (string) config('wallet.currency', 'EUR'));

        return match ($code) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            default => $code,
        };
    }
}
