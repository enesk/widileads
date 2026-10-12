<?php

declare(strict_types=1);

namespace App\Mail\Onboarding;

use App\Constants\OnboardingMailKey;
use App\Services\Onboarding\OnboardingMailContent;

/**
 * Tag 3: "Diese Anfragen warten auf dich" (LP-ONBOARD-014).
 *
 * Bis zu drei Vorschaukarten aus den Kaufkriterien dieses Kaeufers --
 * anonymisiert, ueber LeadPresenter::forTeamView(). Die Mail baut die
 * Maskierung nicht nach und kennt die Kontaktspalten nicht.
 *
 * Abbruchregeln im Job: nur wenn noch nichts gekauft wurde UND mindestens
 * drei passende Anfragen im Marktplatz liegen. Eine Mail mit einer einzigen
 * Karte waere ein Argument gegen den Marktplatz.
 */
class LeadsWaitingMail extends OnboardingMailable
{
    public function key(): OnboardingMailKey
    {
        return OnboardingMailKey::LEADS_WAITING;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $content = app(OnboardingMailContent::class);

        return [
            'cards' => $content->previewCards($this->workspace),
            'total' => $content->matchingLeadCount($this->workspace),
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $this->workspace->uuid]),
        ];
    }
}
