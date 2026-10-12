<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Die Guthabenpakete, die ein Kaeufer zur Auswahl bekommt.
 *
 * Zwei Stellen zeigen dieselben Pakete: die Aufladeseite (LP-PORTAL-014) und
 * der Zahlungsschritt des Einrichtungs-Wizards (LP-ONBOARD-010). Beide lesen
 * die Betraege aus config('wallet.topup_presets_cents') und stutzen sie auf
 * die zulaessige Spanne -- gaebe es diese Regel zweimal, zeigte der Wizard ein
 * Paket, das die Aufladeseite als zu klein ablehnt.
 *
 * Kein Betrag steht im Markup und keiner in dieser Klasse: Hier steht nur,
 * welche der konfigurierten Betraege taugen und welcher davon als "Beliebt"
 * hervorgehoben wird.
 */
final class TopupPackages
{
    /**
     * Die Vorschlaege aufsteigend, ohne Doppelte und ohne Werte ausserhalb der
     * Spanne -- ein Vorschlag unter dem Mindestbetrag fuehrte nur in die
     * Fehlermeldung des WalletTopupController.
     *
     * Hervorgehoben ist der mittlere Vorschlag ("Beliebt"). Bei zwei
     * Vorschlaegen ist das der zweite, bei einem gibt es keine Hervorhebung.
     *
     * `leads` ist der Gegenwert zum uebergebenen Leadpreis -- eine
     * Orientierung und keine Zusage, weil jeder Verkaeufer seinen Preis selbst
     * setzt.
     *
     * @return list<array{euro: int, leads: int, popular: bool}>
     */
    public static function forLeadPrice(int $leadPriceCents): array
    {
        $leadPriceCents = max(1, $leadPriceCents);

        $euros = array_map(
            static fn (int $cents): int => intdiv($cents, 100),
            array_map('intval', (array) config('wallet.topup_presets_cents', [])),
        );

        $minEuro = self::minEuro();
        $maxEuro = self::maxEuro();

        $euros = array_values(array_filter(
            array_unique($euros),
            static fn (int $euro): bool => $euro >= $minEuro && $euro <= $maxEuro,
        ));

        sort($euros);

        $popular = count($euros) > 1 ? intdiv(count($euros) - 1, 2) : -1;

        return array_values(array_map(
            static fn (int $euro, int $index): array => [
                'euro' => $euro,
                'leads' => intdiv($euro * 100, $leadPriceCents),
                'popular' => $index === $popular,
            ],
            $euros,
            array_keys($euros),
        ));
    }

    /**
     * Kleinster zulaessiger Betrag in ganzen Euro. Cent-Betraege sind nicht
     * moeglich: Die Menge im Warenkorb des Checkouts ist der Betrag in Euro.
     */
    public static function minEuro(): int
    {
        return (int) ceil(((int) config('wallet.topup_min_cents')) / 100);
    }

    public static function maxEuro(): int
    {
        return intdiv((int) config('wallet.topup_max_cents'), 100);
    }
}
