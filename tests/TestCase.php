<?php

namespace Tests;

use App\Services\CurrencyService;
use App\Services\TenantPermissionService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;
use PDOException;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Funnel-Feature-Flags (siehe config/funnel.php), die fuer diese Testklasse
     * aktiv sein muessen. Sie werden gesetzt, bevor die Anwendung gebaut wird,
     * damit auch Routen und Navigationseintraege registriert werden.
     *
     * @var list<string>
     */
    protected array $enabledFunnelFeatures = [];

    /**
     * Die Test-Datenbank wird einmal je Prozess angelegt, nicht je Testfall.
     */
    private static bool $testDatabaseEnsured = false;

    /**
     * Statische Caches der Anwendung, die einen einzelnen Request ueberdauern.
     *
     * Alle Testfaelle laufen im selben Prozess, deshalb wuerde ein solcher
     * Cache von einem Test in den naechsten lecken und das Ergebnis von der
     * Ausfuehrungsreihenfolge abhaengig machen (FB-040). Jeder Eintrag ist ein
     * Callable, das den jeweiligen Cache leert.
     *
     * Neue statische Caches in app/ gehoeren hier hinein.
     *
     * @return list<callable(): void>
     */
    protected function applicationStateFlushers(): array
    {
        return [
            CurrencyService::flushCache(...),
            TenantPermissionService::flushPermissionCache(...),
        ];
    }

    public function createApplication(): Application
    {
        self::useIsolatedTestDatabase();

        $this->withFunnelFeatureEnv(true);

        try {
            $app = parent::createApplication();
        } finally {
            // Die Env-Variablen werden direkt wieder entfernt, damit sie nicht in
            // andere Testklassen desselben Prozesses lecken. Die bereits gebaute
            // Anwendung hat die Werte zu diesem Zeitpunkt in ihrer Config.
            $this->withFunnelFeatureEnv(false);
        }

        self::ensureTestDatabaseExists($app);

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Vor jedem Test leeren, damit ein Test auch dann sauber startet, wenn
        // ein vorheriger abgebrochen ist, bevor sein tearDown lief.
        $this->flushApplicationState();
    }

    protected function tearDown(): void
    {
        $this->flushApplicationState();

        parent::tearDown();
    }

    protected function flushApplicationState(): void
    {
        foreach ($this->applicationStateFlushers() as $flush) {
            $flush();
        }
    }

    /**
     * Gibt jeder Arbeitskopie ihre eigene Test-Datenbank (FB-040).
     *
     * RefreshDatabase baut die Datenbank zu Beginn eines Laufs per
     * migrate:fresh neu auf. Teilen sich mehrere Checkouts denselben Namen,
     * reisst ein parallel laufender Testlauf dem anderen die Tabellen weg -
     * mit Fehlern, die nichts mit dem gepruefften Code zu tun haben.
     *
     * Ein ausdruecklich gesetztes DB_DATABASE hat Vorrang, damit CI oder ein
     * gezielter Lauf weiterhin die Datenbank frei waehlen koennen.
     */
    private static function useIsolatedTestDatabase(): void
    {
        if (self::environmentValue('DB_DATABASE') !== null) {
            return;
        }

        $checkout = dirname(__DIR__);
        $readableName = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', basename($checkout)));
        $name = 'funnel_test_'.trim($readableName, '_').'_'.substr(sha1($checkout), 0, 8);

        putenv('DB_DATABASE='.$name);
        $_ENV['DB_DATABASE'] = $name;
        $_SERVER['DB_DATABASE'] = $name;
    }

    /**
     * Legt die Test-Datenbank an, falls sie noch nicht existiert.
     *
     * Ohne diesen Schritt scheitert der erste Lauf in einer frischen
     * Arbeitskopie an einer Datenbank, die es noch nicht gibt.
     *
     * Zur DDL-Regel (kein Schema-Befehl im Testkoerper, weil MySQL dabei die
     * laufende Transaktion committet und RefreshDatabase aushebelt): Diese
     * Stelle faellt formal darunter, ist aber unkritisch. Sie laeuft auf einer
     * eigenen PDO-Verbindung - der implizite Commit wirkt nur in derselben
     * Sitzung -, genau einmal je Prozess, und vor der ersten Transaktion.
     * Geprueft in FB-027; bitte nicht erneut nachrechnen.
     */
    private static function ensureTestDatabaseExists(Application $app): void
    {
        if (self::$testDatabaseEnsured) {
            return;
        }

        self::$testDatabaseEnsured = true;

        /** @var Repository $config */
        $config = $app->make('config');

        $connection = (string) $config->get('database.default');
        $settings = (array) $config->get('database.connections.'.$connection, []);

        if (($settings['driver'] ?? null) !== 'mysql') {
            return;
        }

        $database = (string) ($settings['database'] ?? '');

        if ($database === '') {
            return;
        }

        $dsn = sprintf('mysql:host=%s;port=%s', $settings['host'] ?? '127.0.0.1', $settings['port'] ?? '3306');

        try {
            $pdo = new PDO($dsn, (string) ($settings['username'] ?? ''), (string) ($settings['password'] ?? ''));
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
                str_replace('`', '', $database),
                $settings['charset'] ?? 'utf8mb4',
                $settings['collation'] ?? 'utf8mb4_unicode_ci',
            ));
        } catch (PDOException $exception) {
            throw new RuntimeException(sprintf(
                'Die Test-Datenbank "%s" konnte nicht angelegt werden: %s'."\n"
                .'Lege sie von Hand an oder setze DB_DATABASE auf eine vorhandene Datenbank.',
                $database,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }

    private static function environmentValue(string $key): ?string
    {
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $value = getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function withFunnelFeatureEnv(bool $enabled): void
    {
        foreach ($this->enabledFunnelFeatures as $feature) {
            $key = 'FUNNEL_FEATURE_'.strtoupper($feature).'_ENABLED';

            if ($enabled) {
                putenv($key.'=true');
                $_ENV[$key] = 'true';
                $_SERVER[$key] = 'true';

                continue;
            }

            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}
