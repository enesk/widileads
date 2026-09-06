<?php

declare(strict_types=1);

namespace App\Listeners\Audit;

use App\Constants\AuditAction;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;

/**
 * FB-005: Haelt jede erfolgreiche Anmeldung im Audit-Log fest.
 *
 * Bewusst nicht in die Queue gelegt: Der Eintrag braucht den Request-Kontext
 * (IP-Hash) und soll auch dann existieren, wenn die Queue steht.
 */
class LogSuccessfulLogin
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->auditLogger->log(
            AuditAction::USER_LOGGED_IN,
            subject: $user,
            payload: [
                'guard' => $event->guard,
                'remember' => $event->remember,
            ],
            user: $user,
        );
    }
}
