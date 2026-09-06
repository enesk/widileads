<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Die Begruendung eines manuellen Zustandswechsels genuegt nicht (FB-036).
 *
 * Ein Zwangsstatuswechsel umgeht den normalen fachlichen Ablauf. Er ist nur
 * dann vertretbar, wenn spaeter nachvollziehbar ist, warum jemand ihn
 * vorgenommen hat -- eine leere oder dreiwoertige Begruendung leistet das nicht.
 */
class InvalidLeadStateJustification extends RuntimeException
{
    public static function tooShort(int $minimumLength): self
    {
        return new self(__('funnel.lead.errors.justification_too_short', [
            'min' => $minimumLength,
        ]));
    }
}
