<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Constants\LeadState;
use App\Constants\LeadTransitions;
use RuntimeException;

/**
 * Ein Lead sollte in einen Zustand wechseln, der von seinem aktuellen Zustand
 * aus nicht erreichbar ist (FB-030).
 *
 * Erlaubt ist ausschliesslich, was in LeadTransitions::TABLE steht. Diese
 * Ausnahme ist damit kein Sonderfall, sondern der Normalfall fuer jeden
 * Aufruf, der die Zustandsmaschine umgehen wollte.
 */
class IllegalLeadTransition extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly LeadState $from,
        public readonly LeadState $to,
    ) {
        parent::__construct($message);
    }

    /**
     * Der Uebergang steht nicht in LeadTransitions::TABLE.
     */
    public static function between(LeadState $from, LeadState $to): self
    {
        $allowed = LeadTransitions::TABLE[$from->value] ?? [];

        return new self(
            __('funnel.lead.errors.illegal_transition', [
                'from' => $from->label(),
                'to' => $to->label(),
                'allowed' => $allowed === []
                    ? __('funnel.lead.errors.no_transition_allowed')
                    : implode(', ', array_map(
                        static fn (string $state): string => LeadState::from($state)->label(),
                        $allowed,
                    )),
            ]),
            $from,
            $to,
        );
    }

    /**
     * Zwischen Pruefung und Schreiben hat ein anderer Vorgang den Zustand
     * veraendert. Tritt beim gleichzeitigen Zugriff zweier Kaeufer auf
     * denselben Lead auf (FB-054) -- genau einer gewinnt, der andere landet hier.
     */
    public static function raced(LeadState $expected, LeadState $actual, LeadState $to): self
    {
        return new self(
            __('funnel.lead.errors.concurrent_transition', [
                'expected' => $expected->label(),
                'actual' => $actual->label(),
                'to' => $to->label(),
            ]),
            $actual,
            $to,
        );
    }
}
