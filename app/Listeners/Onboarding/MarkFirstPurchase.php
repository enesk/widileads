<?php

declare(strict_types=1);

namespace App\Listeners\Onboarding;

use App\Constants\OnboardingStep;
use App\Events\Lead\LeadPurchased;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Hakt `first_purchase` ab, sobald ein Lead gekauft ist (LP-ONBOARD-012).
 *
 * `LeadPurchased` feuert erst, wenn Kaufbeleg, Abbuchung und Zustandswechsel
 * festgeschrieben sind -- der Haken steht also nie vor dem Kauf. Idempotent
 * ueber den Unique-Index im OnboardingService: Ab dem zweiten Kauf passiert
 * hier nichts mehr.
 *
 * `$event->automatic` wird nicht unterschieden: Auch ein automatisch
 * zugeteilter Lead ist ein gekaufter Lead, und die Checkliste fragt nach dem
 * Bestand, nicht nach dem Weg dorthin.
 */
class MarkFirstPurchase implements ShouldQueue
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    public function handle(LeadPurchased $event): void
    {
        $this->onboarding->complete(
            $event->buyer,
            OnboardingStep::FIRST_PURCHASE,
            $event->actor,
            ['lead_purchase_id' => (int) $event->purchase->getKey()],
        );
    }
}
