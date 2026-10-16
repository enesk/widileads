<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Ergebnis einer zurueckgezogenen Rufnummer (Ticket #1).
 *
 * Die Oberflaeche braucht mehr als "hat geklappt": Ist die Loeschung bei
 * Twilio gescheitert, war der Vorgang bei uns trotzdem erfolgreich -- die
 * Nummer ist freigegeben --, aber im Twilio-Konto steht sie weiter. Das ist
 * eine Nachricht an den Betreiber, nicht an den Nutzer.
 *
 * @param  bool  $released  Lag ueberhaupt etwas zum Zuruecknehmen vor?
 * @param  string|null  $phoneNumber  Die zurueckgenommene Nummer in E.164.
 * @param  int  $deletedAtProvider  Entfernte Eintraege im Twilio-Konto.
 * @param  string|null  $providerError  Meldung, falls die Loeschung dort scheiterte.
 */
final readonly class CallerIdRelease
{
    public function __construct(
        public bool $released,
        public ?string $phoneNumber = null,
        public int $deletedAtProvider = 0,
        public ?string $providerError = null,
    ) {}
}
