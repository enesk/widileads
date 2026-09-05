<?php

declare(strict_types=1);

namespace App\Console;

/**
 * Entscheidet, ob datenvernichtende Artisan-Befehle ausgefuehrt werden duerfen.
 *
 * Die Befehle sind grundsaetzlich in JEDER Umgebung gesperrt -- auch lokal und
 * auch mit --force. Einzige Ausnahme ist die Testsuite, die ihre eigene
 * Datenbank aufbauen koennen muss: dafuer muessen APP_ENV=testing und
 * FUNNEL_ALLOW_DESTRUCTIVE=1 gleichzeitig gesetzt sein.
 */
final class DestructiveCommandGuard
{
    /**
     * @var list<string>
     */
    public const BLOCKED_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    /**
     * Sind die destruktiven Befehle in der aktuellen Umgebung erlaubt?
     */
    public function isAllowed(): bool
    {
        if (! app()->environment('testing')) {
            return false;
        }

        return filter_var(
            config('funnel.allow_destructive_commands'),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * Muessen die destruktiven Befehle blockiert werden?
     */
    public function shouldBlock(): bool
    {
        return ! $this->isAllowed();
    }
}
