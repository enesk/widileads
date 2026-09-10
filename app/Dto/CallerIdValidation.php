<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Antwort von Twilio auf eine angeforderte Rufnummern-Bestaetigung (FB-080).
 *
 * `validationCode` ist der Code, den Twilio am Telefon ansagt. Er wird dem
 * Mitarbeiter angezeigt, damit er hoert, was er erwartet -- eingegeben wird er
 * nirgends.
 */
final readonly class CallerIdValidation
{
    public function __construct(
        public string $callSid,
        public string $validationCode,
    ) {}
}
