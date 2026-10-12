<?php

declare(strict_types=1);

namespace App\Mail\Onboarding;

use App\Constants\OnboardingMailKey;

/**
 * Tag 1: wie abgerechnet wird (LP-ONBOARD-014).
 *
 * Dieselben drei Zustaende wie die animierte Erklaerung auf dem
 * Abschluss-Screen (LP-ONBOARD-010) -- es klingelt, der Anruf wird
 * angenommen und kostet, er wird nicht angenommen und kostet nicht. In der
 * Mail als drei Zeilen mit Symbol, weil eine Animation dort nicht ankommt.
 * Die Texte kommen aus demselben Sprachzweig wie der Player, damit beide
 * Stellen nicht auseinanderlaufen.
 *
 * Abbruchregel im Job: Wer schon gekauft hat, braucht sie nicht mehr.
 */
class BillingExplainedMail extends OnboardingMailable
{
    public function key(): OnboardingMailKey
    {
        return OnboardingMailKey::BILLING_EXPLAINED;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $this->workspace->uuid]),
            // Dieselbe Quelle wie der Player auf dem Abschluss-Screen:
            // config('lead_calls.*'). In der Mail steht bewusst kein Betrag
            // -- einen konkreten Leadpreis gibt es hier nicht, und ein
            // erfundener waere eine Zusage.
            'seconds' => (int) config('lead_calls.answered_min_seconds'),
            'attempts' => (int) config('lead_calls.unreachable_attempts'),
            'days' => (int) config('lead_calls.unreachable_min_days'),
            'hours' => (int) config('lead_calls.retry_min_hours'),
        ];
    }
}
