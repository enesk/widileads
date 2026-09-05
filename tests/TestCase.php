<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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

    public function createApplication(): Application
    {
        $this->withFunnelFeatureEnv(true);

        try {
            return parent::createApplication();
        } finally {
            // Die Env-Variablen werden direkt wieder entfernt, damit sie nicht in
            // andere Testklassen desselben Prozesses lecken. Die bereits gebaute
            // Anwendung hat die Werte zu diesem Zeitpunkt in ihrer Config.
            $this->withFunnelFeatureEnv(false);
        }
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
