<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use RuntimeException;

/**
 * Zu viele Bestaetigungsanrufe in zu kurzer Zeit (Ticket #97).
 *
 * Geworfen, BEVOR eine Zeile entsteht und bevor Twilio gewaehlt wird. Jeder
 * Bestaetigungsanruf kostet Geld und laesst bei einem echten Menschen ein
 * Telefon klingeln -- das ist der Grund fuer die Grenze, und es ist derselbe
 * Grund, aus dem der SMS-Weg eine hatte.
 *
 * `$retryAfterSeconds` ist die Wartezeit, die der Oberflaeche als Zaehler
 * dient. Null heisst: Das Zeitfenster ist voll, ohne dass sich eine naechste
 * Freigabe auf die Sekunde sagen laesst.
 */
class CallerIdValidationThrottled extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds, string $message = 'Zu viele Bestaetigungsanrufe.')
    {
        parent::__construct($message);
    }
}
