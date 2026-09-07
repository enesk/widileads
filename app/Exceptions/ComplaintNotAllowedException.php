<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Diese Reklamation ist nicht moeglich (FB-058).
 *
 * Meist harmlos: Die Frist ist abgelaufen, oder der Kauf ist bereits
 * reklamiert. Die Oberflaeche zeigt das als Hinweis, nicht als Absturz.
 */
class ComplaintNotAllowedException extends RuntimeException
{
    public static function notYourPurchase(): self
    {
        return new self(__('marketplace.complaint.errors.not_your_purchase'));
    }

    public static function unsupportedState(): self
    {
        return new self(__('marketplace.complaint.errors.unsupported_state'));
    }

    public static function reasonRequired(): self
    {
        return new self(__('marketplace.complaint.errors.reason_required'));
    }

    public static function leadAlreadySettled(): self
    {
        return new self(__('marketplace.complaint.errors.lead_already_settled'));
    }

    public static function deadlineElapsed(): self
    {
        return new self(__('marketplace.complaint.errors.deadline_elapsed'));
    }

    public static function alreadyFiled(): self
    {
        return new self(__('marketplace.complaint.errors.already_filed'));
    }

    public static function alreadyDecided(): self
    {
        return new self(__('marketplace.complaint.errors.already_decided'));
    }
}
