<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\BlockedDestructiveCommand;
use App\Console\DestructiveCommandGuard;
use Illuminate\Console\Application as Artisan;
use Illuminate\Support\ServiceProvider;

/**
 * Ersetzt datenvernichtende Artisan-Befehle durch einen Platzhalter, der mit
 * Exit-Code 1 abbricht (siehe DestructiveCommandGuard).
 *
 * Die Entscheidung faellt erst, wenn die Artisan-Application gebaut wird, damit
 * sie die zu dem Zeitpunkt gueltige Konfiguration sieht.
 */
class DestructiveCommandGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DestructiveCommandGuard::class);
    }

    public function boot(): void
    {
        Artisan::starting(function (Artisan $artisan): void {
            if (! $this->app->make(DestructiveCommandGuard::class)->shouldBlock()) {
                return;
            }

            foreach (DestructiveCommandGuard::BLOCKED_COMMANDS as $command) {
                $artisan->add(new BlockedDestructiveCommand($command));
            }
        });
    }
}
