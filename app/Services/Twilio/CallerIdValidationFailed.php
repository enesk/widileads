<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use RuntimeException;

/**
 * Twilio hat die angeforderte Bestaetigung abgelehnt (FB-080).
 *
 * Die Meldung von Twilio steht im Klartext darin und wird dem Mitarbeiter
 * nicht gezeigt -- sie gehoert ins Log, nicht in die Oberflaeche.
 */
class CallerIdValidationFailed extends RuntimeException {}
