<?php

declare(strict_types=1);

namespace App\Mail\Onboarding;

use App\Constants\OnboardingMailKey;
use App\Services\Onboarding\OnboardingMailContent;

/**
 * Tag 7: der letzte Anstupser (LP-ONBOARD-014).
 *
 * Der Ring-Fortschritt des Dashboards steht hier als Zahl ("3 von 5
 * Schritten erledigt") -- ein gezeichneter Ring waere in der Mail entweder
 * ein Bild, das geblockt wird, oder eine Tabelle, die nirgends gleich
 * aussieht. Gezaehlt werden dieselben fuenf Zeilen wie in der
 * Aktivierungskarte.
 *
 * Abbruchregel im Job: nur an Kaeufer, die noch nicht aktiviert sind -- also
 * noch keinen gekauften Lead angerufen haben.
 */
class NudgeMail extends OnboardingMailable
{
    public function key(): OnboardingMailKey
    {
        return OnboardingMailKey::NUDGE;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        $content = app(OnboardingMailContent::class);
        $progress = $content->checklistProgress($this->workspace);

        return [
            'action' => $content->nextAction($this->workspace),
            'done' => $progress['done'],
            'total' => $progress['total'],
            'marketplaceUrl' => route('portal.marketplace', ['tenant' => $this->workspace->uuid]),
        ];
    }
}
