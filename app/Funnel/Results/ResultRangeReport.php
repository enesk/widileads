<?php

declare(strict_types=1);

namespace App\Funnel\Results;

/**
 * Ergebnis der Bereichspruefung (FB-013).
 *
 * Traegt die gefundenen Maengel als fertige deutsche Meldungen, damit Publish
 * (FB-014) und Builder (FB-016) sie unveraendert anzeigen koennen.
 */
class ResultRangeReport
{
    /**
     * @param  list<string>  $overlaps  ueberschneidende Bereiche
     * @param  list<string>  $gaps  Punktzahlen ohne Ergebnis
     * @param  list<string>  $invalidRanges  Bereiche, deren Untergrenze ueber der Obergrenze liegt
     */
    public function __construct(
        public readonly array $overlaps = [],
        public readonly array $gaps = [],
        public readonly array $invalidRanges = [],
    ) {}

    public function isValid(): bool
    {
        return $this->overlaps === [] && $this->gaps === [] && $this->invalidRanges === [];
    }

    /**
     * Alle Maengel als flache Liste von Meldungen.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return [...$this->invalidRanges, ...$this->overlaps, ...$this->gaps];
    }
}
