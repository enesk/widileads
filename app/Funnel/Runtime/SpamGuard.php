<?php

declare(strict_types=1);

namespace App\Funnel\Runtime;

use App\Models\PublicSession;

/**
 * Prueft eine Einreichung auf die drei billigen Spam-Merkmale (FB-023).
 *
 * Bewusst ohne Captcha im Standardpfad: Ein Captcha kostet jeden ehrlichen
 * Endkunden Zeit und einige den Abschluss, waehrend es den ernsthaften Angreifer
 * kaum aufhaelt. Honeypot, Zeitfalle und Rate-Limit kosten den Endkunden nichts.
 *
 * Bewertet wird hier nichts -- siehe SpamAssessment.
 */
class SpamGuard
{
    public function __construct(private readonly DuplicateLeadFinder $duplicateLeadFinder) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  string|null  $honeypotValue  Inhalt des versteckten Felds; ein Mensch fuellt es nie
     */
    public function assess(
        PublicSession $session,
        array $answers,
        ?string $honeypotValue,
        ?int $funnelId = null,
    ): SpamAssessment {
        $secondsOnFunnel = (int) $session->started_at->diffInSeconds(now(), absolute: true);

        return new SpamAssessment(
            honeypotTripped: is_string($honeypotValue) && trim($honeypotValue) !== '',
            submittedTooFast: $secondsOnFunnel < (int) config('funnel.public.min_seconds_before_submit'),
            rateLimited: $this->isRateLimited($session),
            duplicateOfLeadId: $this->duplicateLeadFinder->findRecentDuplicate($funnelId, $answers),
            secondsOnFunnel: $secondsOnFunnel,
        );
    }

    /**
     * Zaehlt abgeschlossene Einreichungen derselben Herkunft in der letzten
     * Stunde. Verglichen wird der IP-Hash aus FB-022 -- die Adresse selbst liegt
     * nirgends vor, und genau deshalb ist der Hash ueberall derselbe.
     */
    private function isRateLimited(PublicSession $session): bool
    {
        if ($session->ip_hash === null) {
            return false;
        }

        $limit = (int) config('funnel.public.rate_limit_per_hour');

        if ($limit <= 0) {
            return false;
        }

        $submissions = PublicSession::query()
            ->where('ip_hash', $session->ip_hash)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subHour())
            ->count();

        return $submissions >= $limit;
    }
}
