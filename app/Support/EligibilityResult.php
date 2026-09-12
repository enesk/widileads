<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Das Ergebnis der Eignungspruefung fuer Pay as you go (LP-POSTPAID-006).
 *
 * Die Pruefung ist ein Vorfilter und keine Entscheidung: Freigeschaltet wird
 * von Hand. Deshalb traegt das Ergebnis drei Dinge und nicht nur ein `bool`:
 *
 * - `eligible` -- ob alle Regeln erfuellt sind; nur dann darf beantragt werden.
 * - `reasons` -- je nicht erfuellter Regel ein deutscher Satz, der dem Kaeufer
 *   im Portal angezeigt wird. Erfuellte Regeln stehen nicht darin, sonst
 *   liest sich eine bestandene Pruefung wie eine Maengelliste.
 * - `snapshot` -- die Zahlen, auf denen das Urteil beruht. Sie landen in
 *   `postpaid_applications.eligibility_snapshot` und im Admin. Ohne sie waere
 *   eine spaetere Ablehnung oder ein Zahlungsausfall nicht mehr
 *   nachvollziehbar, weil die Werte bis dahin weitergelaufen sind.
 *
 * Bewusst unveraenderlich: Ein Ergebnis, das sich nach der Pruefung noch
 * aendern laesst, ist als Beleg wertlos.
 */
final class EligibilityResult
{
    /**
     * @param  list<string>  $reasons  Deutsche Begruendungen je nicht erfuellter Regel
     * @param  array<string, mixed>  $snapshot  Die Zahlen zum Zeitpunkt der Pruefung
     */
    public function __construct(
        public readonly bool $eligible,
        public readonly array $reasons,
        public readonly array $snapshot,
    ) {}

    /**
     * Ergebnis aus der Liste der nicht erfuellten Regeln: geeignet ist, wer
     * keine offen hat.
     *
     * @param  list<string>  $reasons
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromReasons(array $reasons, array $snapshot): self
    {
        return new self($reasons === [], array_values($reasons), $snapshot);
    }

    /**
     * Die Begruendungen als ein Satz -- fuer Protokoll und Mail, wo keine
     * Liste dargestellt werden kann.
     */
    public function reasonText(): string
    {
        return implode(' ', $this->reasons);
    }

    /**
     * Ergebnis samt Begruendungen als Array. Genau diese Form wird am Antrag
     * gespeichert: Der Beleg soll auch das Urteil enthalten, nicht nur die
     * Rohwerte.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->snapshot + [
            'eligible' => $this->eligible,
            'reasons' => $this->reasons,
        ];
    }
}
