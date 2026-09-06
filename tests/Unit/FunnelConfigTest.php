<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FB-004: config/funnel.php haelt alle Schwellwerte der Funnel-Builder-Plattform.
 * Spaetere Tickets duerfen diese Werte nicht hartkodieren, deshalb wird hier
 * abgesichert, dass Schluessel, Typen und Defaults vorhanden bleiben.
 */
class FunnelConfigTest extends TestCase
{
    /**
     * @return array<string, array{string, mixed}>
     */
    public static function defaultValueProvider(): array
    {
        return [
            'lead.default_price' => ['funnel.lead.default_price', 15.00],
            'lead.reservation_ttl' => ['funnel.lead.reservation_ttl', 10],
            'lead.retention_days' => ['funnel.lead.retention_days', 730],
            'lead.stale_after_days' => ['funnel.lead.stale_after_days', 3],
            'public.rate_limit_per_hour' => ['funnel.public.rate_limit_per_hour', 20],
            'public.min_seconds_before_submit' => ['funnel.public.min_seconds_before_submit', 30],
            'call.answered_after_seconds' => ['funnel.call.answered_after_seconds', 30],
            'call.max_failed_attempts' => ['funnel.call.max_failed_attempts', 3],
            'call.min_gap_hours' => ['funnel.call.min_gap_hours', 2],
            'call.min_days' => ['funnel.call.min_days', 2],
            'call.deadline_days' => ['funnel.call.deadline_days', 7],
            'api.token_expiration_days' => ['funnel.api.token_expiration_days', 0],
            'api.max_tokens_per_tenant' => ['funnel.api.max_tokens_per_tenant', 10],
        ];
    }

    #[DataProvider('defaultValueProvider')]
    public function test_config_key_has_the_documented_default(string $key, mixed $expected): void
    {
        $this->assertSame($expected, config($key));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function envOverrideProvider(): array
    {
        return [
            'lead.default_price' => ['funnel.lead.default_price', 'FUNNEL_LEAD_DEFAULT_PRICE'],
            'lead.reservation_ttl' => ['funnel.lead.reservation_ttl', 'FUNNEL_LEAD_RESERVATION_TTL'],
            'lead.retention_days' => ['funnel.lead.retention_days', 'FUNNEL_LEAD_RETENTION_DAYS'],
            'lead.stale_after_days' => ['funnel.lead.stale_after_days', 'FUNNEL_LEAD_STALE_AFTER_DAYS'],
            'public.rate_limit_per_hour' => ['funnel.public.rate_limit_per_hour', 'FUNNEL_PUBLIC_RATE_LIMIT_PER_HOUR'],
            'public.min_seconds_before_submit' => ['funnel.public.min_seconds_before_submit', 'FUNNEL_PUBLIC_MIN_SECONDS_BEFORE_SUBMIT'],
            'call.answered_after_seconds' => ['funnel.call.answered_after_seconds', 'FUNNEL_CALL_ANSWERED_AFTER_SECONDS'],
            'call.max_failed_attempts' => ['funnel.call.max_failed_attempts', 'FUNNEL_CALL_MAX_FAILED_ATTEMPTS'],
            'call.min_gap_hours' => ['funnel.call.min_gap_hours', 'FUNNEL_CALL_MIN_GAP_HOURS'],
            'call.min_days' => ['funnel.call.min_days', 'FUNNEL_CALL_MIN_DAYS'],
            'call.deadline_days' => ['funnel.call.deadline_days', 'FUNNEL_CALL_DEADLINE_DAYS'],
            'features.blog' => ['funnel.features.blog', 'FUNNEL_FEATURE_BLOG_ENABLED'],
            'features.roadmap' => ['funnel.features.roadmap', 'FUNNEL_FEATURE_ROADMAP_ENABLED'],
            'features.announcements' => ['funnel.features.announcements', 'FUNNEL_FEATURE_ANNOUNCEMENTS_ENABLED'],
            'features.referral' => ['funnel.features.referral', 'FUNNEL_FEATURE_REFERRAL_ENABLED'],
            'allow_destructive_commands' => ['funnel.allow_destructive_commands', 'FUNNEL_ALLOW_DESTRUCTIVE'],
            'api.token_expiration_days' => ['funnel.api.token_expiration_days', 'FUNNEL_API_TOKEN_EXPIRATION_DAYS'],
            'api.max_tokens_per_tenant' => ['funnel.api.max_tokens_per_tenant', 'FUNNEL_API_MAX_TOKENS_PER_TENANT'],
        ];
    }

    #[DataProvider('envOverrideProvider')]
    public function test_config_key_can_be_overridden_via_env(string $key, string $envKey): void
    {
        $configFile = file_get_contents(config_path('funnel.php'));

        $this->assertNotFalse($configFile);
        $this->assertStringContainsString(
            "env('".$envKey."'",
            $configFile,
            sprintf('Der Config-Key "%s" muss ueber die Env-Variable "%s" ueberschreibbar sein.', $key, $envKey),
        );
        $this->assertNotNull(config($key), sprintf('Der Config-Key "%s" fehlt.', $key));
    }

    public function test_every_config_key_is_documented_with_a_comment(): void
    {
        $configFile = file_get_contents(config_path('funnel.php'));

        $this->assertNotFalse($configFile);

        foreach (self::envOverrideProvider() as [$key, $envKey]) {
            $position = strpos($configFile, "env('".$envKey."'");

            $this->assertNotFalse($position);

            $precedingLines = substr($configFile, 0, $position);

            $this->assertStringContainsString(
                '//',
                substr($precedingLines, -400),
                sprintf('Der Config-Key "%s" braucht einen erklaerenden Kommentar.', $key),
            );
        }
    }

    public function test_feature_flags_are_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('funnel.features.blog'));
        $this->assertFalse((bool) config('funnel.features.roadmap'));
        $this->assertFalse((bool) config('funnel.features.announcements'));
        $this->assertFalse((bool) config('funnel.features.referral'));
    }
}
