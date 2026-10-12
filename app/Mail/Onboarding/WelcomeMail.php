<?php

declare(strict_types=1);

namespace App\Mail\Onboarding;

use App\Constants\OnboardingMailKey;
use App\Services\Onboarding\OnboardingMailContent;

/**
 * Tag 0: "Du bist startklar" (LP-ONBOARD-014).
 *
 * Die einzige Mail der Strecke ohne Abbruchregel -- sie geht immer raus,
 * sofort nach dem Wizard oder nach dem Ueberspringen. Ein Knopf, und zwar
 * der auf den naechsten offenen Schritt: Wer gerade aufgeladen hat, soll
 * nicht "Guthaben aufladen" lesen.
 */
class WelcomeMail extends OnboardingMailable
{
    public function key(): OnboardingMailKey
    {
        return OnboardingMailKey::WELCOME;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $content = app(OnboardingMailContent::class);

        return [
            'action' => $content->nextAction($this->workspace),
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $this->workspace->uuid]),
        ];
    }
}
