<?php

declare(strict_types=1);

namespace App\Services\Twilio;

use RuntimeException;

/**
 * Twilio konnte den Anruf nicht starten (FB-081).
 */
class OutboundCallFailed extends RuntimeException {}
