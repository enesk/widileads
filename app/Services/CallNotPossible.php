<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Der Anruf kann nicht gestartet werden (FB-081).
 *
 * Die Begruendung steht als Uebersetzungsschluessel darin, damit die
 * Oberflaeche sie dem Kaeufer zeigen kann, ohne selbst zu entscheiden, was
 * schiefging. Der HTTP-Status gehoert zur Begruendung: Ein bereits laufender
 * Anruf ist ein Konflikt (409), alles andere eine abgelehnte Eingabe (422).
 */
class CallNotPossible extends RuntimeException
{
    /**
     * @param  array<string, string>  $replacements
     */
    final public function __construct(
        string $translationKey,
        private readonly array $replacements = [],
        private readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ) {
        parent::__construct($translationKey);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    public static function because(string $translationKey, array $replacements = []): static
    {
        return new static($translationKey, $replacements);
    }

    /**
     * Fuer diesen Lead laeuft bereits ein Anruf. Ein zweiter Klick darf keinen
     * zweiten Anruf ausloesen -- deshalb 409 und nicht 422.
     */
    public static function alreadyRunning(): static
    {
        return new static('call.attempt.errors.already_running', [], Response::HTTP_CONFLICT);
    }

    public function reason(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, string>
     */
    public function replacements(): array
    {
        return $this->replacements;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * Die fertige Meldung fuer den Kaeufer.
     */
    public function translated(): string
    {
        return __($this->reason(), $this->replacements);
    }
}
