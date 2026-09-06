<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Erlaubte Zustandswechsel eines Leads (FB-030).
 *
 * TABLE ist die einzige Stelle, an der steht, welcher Uebergang fachlich
 * moeglich ist. Alles, was hier nicht steht, ist verboten und fuehrt in
 * App\Services\LeadStateService::transition() zu einer
 * App\Exceptions\IllegalLeadTransition.
 *
 * Bewusste Eigenschaften der Tabelle:
 *
 * - Endzustaende (erreicht, unerreichbar, ungueltig, abgelaufen) haben eine
 *   leere Liste. Aus ihnen fuehrt kein Weg heraus, damit ein festgeschriebener
 *   Preis nie nachtraeglich seine Grundlage verliert (Architekturleitsatz 4).
 * - Ein Zustand ist nie sein eigenes Ziel. Ein "Wechsel" auf den bereits
 *   gesetzten Zustand ist ein Programmierfehler und keine leere Operation --
 *   sonst entstuenden Protokolleintraege ohne Zustandsaenderung.
 */
final class LeadTransitions
{
    /**
     * Erlaubte Ziele je Ausgangszustand: Schluessel und Werte sind die
     * String-Werte von LeadState.
     *
     * @var array<string, list<string>>
     */
    public const TABLE = [

        // Die Pruefung nach dem Anlegen (FB-033) entscheidet: kaufbar, als
        // Spam/Dublette verworfen -- oder der Lead bleibt liegen und
        // verfaellt mit der Aufbewahrungsfrist.
        'neu' => ['verfuegbar', 'ungueltig', 'abgelaufen'],

        // Im Marktplatz: Reservierung durch einen Kaeufer, nachtraegliche
        // Aussortierung oder Ablauf der Aufbewahrungsfrist (FB-037).
        'verfuegbar' => ['reserviert', 'ungueltig', 'abgelaufen'],

        // Reserviert: Kauf, Rueckgabe an den Marktplatz nach Ablauf der
        // Reservierung oder Aussortierung durch den Operator.
        'reserviert' => ['verfuegbar', 'verkauft', 'ungueltig'],

        // Verkauft: der Anrufnachweis (FB-E7) bzw. bis dahin die Reklamation
        // (FB-058) entscheidet ueber die Abrechnung.
        'verkauft' => ['erreicht', 'unerreichbar', 'ungueltig'],

        // Endzustaende.
        'erreicht' => [],
        'unerreichbar' => [],
        'ungueltig' => [],
        'abgelaufen' => [],

    ];

    /**
     * Ist der Wechsel von $from nach $to erlaubt?
     */
    public static function isAllowed(LeadState $from, LeadState $to): bool
    {
        return in_array($to->value, self::TABLE[$from->value] ?? [], true);
    }

    /**
     * Alle Zustaende, die von $from aus erreichbar sind.
     *
     * @return list<LeadState>
     */
    public static function allowedFrom(LeadState $from): array
    {
        return array_map(
            static fn (string $state): LeadState => LeadState::from($state),
            self::TABLE[$from->value] ?? [],
        );
    }
}
