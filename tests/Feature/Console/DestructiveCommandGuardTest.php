<?php

namespace Tests\Feature\Console;

use App\Console\BlockedDestructiveCommand;
use App\Console\DestructiveCommandGuard;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FB-003: migrate:fresh, migrate:refresh, migrate:reset und db:wipe brechen in
 * jeder Umgebung ab - auch mit --force. Einzige Ausnahme ist die Testsuite
 * (APP_ENV=testing UND FUNNEL_ALLOW_DESTRUCTIVE=1), damit sie ihre Datenbank
 * weiterhin aufbauen kann.
 *
 * Die Klasse nutzt bewusst kein RefreshDatabase: die Artisan-Application darf
 * erst gebaut werden, nachdem der Test die Konfiguration umgestellt hat.
 */
class DestructiveCommandGuardTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function blockedCommandProvider(): array
    {
        return [
            'migrate:fresh' => ['migrate:fresh'],
            'migrate:refresh' => ['migrate:refresh'],
            'migrate:reset' => ['migrate:reset'],
            'db:wipe' => ['db:wipe'],
        ];
    }

    #[DataProvider('blockedCommandProvider')]
    public function test_destructive_command_is_aborted(string $command): void
    {
        $this->blockDestructiveCommands();

        $this->artisan($command)
            ->expectsOutputToContain('ist im Funnel Builder gesperrt')
            ->assertExitCode(1);
    }

    #[DataProvider('blockedCommandProvider')]
    public function test_destructive_command_is_aborted_even_with_force(string $command): void
    {
        $this->blockDestructiveCommands();

        $this->artisan($command, ['--force' => true])
            ->expectsOutputToContain('ist im Funnel Builder gesperrt')
            ->assertExitCode(1);
    }

    public function test_blocked_commands_replace_the_original_commands(): void
    {
        $this->blockDestructiveCommands();

        $this->artisan('migrate:fresh')->assertExitCode(1);

        foreach (DestructiveCommandGuard::BLOCKED_COMMANDS as $command) {
            $this->assertInstanceOf(
                BlockedDestructiveCommand::class,
                Artisan::all()[$command],
                sprintf('Der Befehl "%s" wurde nicht durch den Platzhalter ersetzt.', $command),
            );
        }
    }

    public function test_the_test_suite_may_still_run_destructive_commands(): void
    {
        // Standard der Testsuite: APP_ENV=testing und FUNNEL_ALLOW_DESTRUCTIVE=1.
        $this->assertTrue($this->guard()->isAllowed());

        $this->artisan('list');

        $this->assertInstanceOf(FreshCommand::class, Artisan::all()['migrate:fresh']);
    }

    public function test_flag_alone_is_not_enough_outside_the_testing_environment(): void
    {
        config(['funnel.allow_destructive_commands' => true]);

        foreach (['production', 'staging', 'local'] as $environment) {
            $this->app['env'] = $environment;

            $this->assertTrue(
                $this->guard()->shouldBlock(),
                sprintf('In der Umgebung "%s" muessen destruktive Befehle gesperrt sein.', $environment),
            );
        }
    }

    public function test_testing_environment_alone_is_not_enough(): void
    {
        config(['funnel.allow_destructive_commands' => false]);

        $this->assertTrue($this->guard()->shouldBlock());
    }

    private function blockDestructiveCommands(): void
    {
        config(['funnel.allow_destructive_commands' => false]);

        $this->app->setLocale('de');
    }

    private function guard(): DestructiveCommandGuard
    {
        return $this->app->make(DestructiveCommandGuard::class);
    }
}
