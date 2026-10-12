<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use RuntimeException;

/**
 * Twilio Verify hat die Anforderung nicht angenommen (LP-ONBOARD-004).
 *
 * Immer ein Problem auf unserer Seite oder bei Twilio -- fehlende Verify-SID,
 * gesperrtes Konto, nicht erreichbare API. Ein falscher Code ist das nicht:
 * Der kommt als `false` aus checkVerification() zurueck. Die Unterscheidung
 * ist wichtig, weil nur der falsche Code dem Nutzer gezeigt wird; diese
 * Ausnahme gehoert ins Log.
 */
class VerifyRequestFailed extends RuntimeException {}
