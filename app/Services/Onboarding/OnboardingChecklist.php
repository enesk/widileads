<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Constants\OnboardingStep;
use App\Constants\PurchaseStatus;
use App\Models\LeadPurchase;
use App\Models\Tenant;

/**
 * Die fuenf Zeilen der Aktivierungs-Checkliste -- an einer Stelle.
 *
 * Die Karte im Dashboard (LP-ONBOARD-012) und die Mailstrecke
 * (LP-ONBOARD-014) reden ueber dieselbe Liste: dieselbe Reihenfolge,
 * dieselben Sprachschluessel, dieselben Ziele. Vorher leiteten beide sie
 * unabhaengig voneinander ab; eine sechste Zeile waere an einer Stelle
 * gelandet und an der anderen nicht -- die Mail haette "3 von 5" gesagt und
 * das Dashboard "3 von 6". Deshalb steht die Ableitung hier.
 *
 * **Was hier nicht steht:** Die Touren. Sie sind Begleitung und keine
 * Aufgabe, und sie haken sich ohnehin beim ersten Besuch ab. Gezaehlt werden
 * die fuenf Zeilen und nicht die neun Faelle des Enums -- die Mail soll
 * dieselbe Zahl nennen, die der Kaeufer im Dashboard sieht.
 *
 * Der Knopf haengt an genau einer Zeile: der ersten offenen. Zwei
 * Primaerknoepfe in einer Karte lassen den Kaeufer waehlen, wo er nichts zu
 * waehlen hat. Die Mails nehmen genau dieses eine Ziel.
 */
class OnboardingChecklist
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    /**
     * Die Zeilen in Anzeigereihenfolge.
     *
     * Wer den Stand schon geholt hat (die Karte braucht ihn fuer die
     * Sichtbarkeit), gibt ihn mit -- `progress()` ist je Workspace gemerkt,
     * ein zweiter Aufruf kostet also nichts, aber er ist auch nicht noetig.
     *
     * @return list<array{key: string, label: string, done: bool, current: bool, action: array{label: string, url: string}|null}>
     */
    public function rows(Tenant $workspace, ?OnboardingProgress $progress = null): array
    {
        $progress ??= $this->onboarding->progress($workspace);

        // Branchen und Region sind im Wizard zwei Schritte, in der Checkliste
        // eine Zeile: Der Kaeufer legt beides an derselben Stelle fest.
        $criteriaDone = $progress->isCompleted(OnboardingStep::INDUSTRIES_SELECTED)
            && $progress->isCompleted(OnboardingStep::REGION_SELECTED);

        $rows = [
            // Die Rufnummer ist hier immer erledigt: Ohne sie kommt niemand
            // bis ins Dashboard (LP-ONBOARD-003) und erst recht nicht bis zu
            // einer Mail dieser Strecke. Die Zeile steht trotzdem da -- sie
            // ist der erste Haken, den der Kaeufer sieht.
            'phone' => true,
            'criteria' => $criteriaDone,
            'topup' => $progress->isCompleted(OnboardingStep::FIRST_TOPUP),
            'purchase' => $progress->isCompleted(OnboardingStep::FIRST_PURCHASE),
            'call' => $progress->isCompleted(OnboardingStep::FIRST_CALL),
        ];

        $items = [];
        $currentFound = false;

        foreach ($rows as $key => $done) {
            $isCurrent = ! $done && ! $currentFound;
            $currentFound = $currentFound || $isCurrent;

            $items[] = [
                'key' => $key,
                'label' => $this->label($key, $progress),
                'done' => $done,
                'current' => $isCurrent,
                // Das Ziel wird nur fuer die offene Zeile gebraucht -- und
                // nur dort kostet "Jetzt anrufen" eine Abfrage.
                'action' => $isCurrent ? $this->action($key, $workspace) : null,
            ];
        }

        return $items;
    }

    /**
     * Der eine Schritt, zu dem die Mail auffordert -- Beschriftung und Ziel.
     *
     * Das ist das Ziel der ersten offenen Zeile. Null heisst: nichts mehr
     * offen.
     *
     * @return array{label: string, url: string}|null
     */
    public function nextAction(Tenant $workspace, ?OnboardingProgress $progress = null): ?array
    {
        foreach ($this->rows($workspace, $progress) as $row) {
            if ($row['current']) {
                return $row['action'];
            }
        }

        return null;
    }

    /**
     * Der Fortschritt als zwei Zahlen fuer "3 von 5 Schritten erledigt".
     *
     * @return array{done: int, total: int}
     */
    public function counts(Tenant $workspace, ?OnboardingProgress $progress = null): array
    {
        $rows = $this->rows($workspace, $progress);

        return [
            'done' => count(array_filter($rows, static fn (array $row): bool => $row['done'])),
            'total' => count($rows),
        ];
    }

    /**
     * Das Ziel einer Zeile -- Beschriftung und Adresse.
     *
     * Die Rufnummernzeile hat keines: Sie ist schon erledigt, wenn der
     * Kaeufer die Checkliste zu sehen bekommt.
     *
     * @return array{label: string, url: string}|null
     */
    private function action(string $key, Tenant $workspace): ?array
    {
        $uuid = $workspace->uuid;

        $url = match ($key) {
            'criteria' => route('portal.buying-criteria', ['tenant' => $uuid]),
            'topup' => route('portal.wallet', ['tenant' => $uuid]),
            'purchase' => route('portal.marketplace', ['tenant' => $uuid]),
            'call' => $this->oldestOpenPurchaseUrl($workspace),
            default => null,
        };

        if ($url === null) {
            return null;
        }

        return [
            'label' => (string) __('portal.onboarding.activation.actions.'.$key),
            'url' => $url,
        ];
    }

    /**
     * Die Beschriftung einer Zeile.
     *
     * Wer den Wizard uebersprungen hat, liest bei den Kaufkriterien eine
     * Aufforderung statt einer Feststellung: "Branchen und Region festlegen"
     * statt "gewaehlt". Ohne sie sieht er nur einen offenen Haken und erfaehrt
     * nicht, dass er die Auswahl nachholen muss.
     */
    private function label(string $key, OnboardingProgress $progress): string
    {
        if ($key === 'criteria' && $progress->wizardSkipped) {
            return (string) __('portal.onboarding.activation.steps.criteria_todo');
        }

        return (string) __('portal.onboarding.activation.steps.'.$key);
    }

    /**
     * Das Ziel von "Jetzt anrufen": der aelteste noch offene Kauf.
     *
     * Offen heisst reserviert und mit einem Lead, dessen Erreichbarkeit noch
     * nicht entschieden ist -- dieselbe Lesart wie im Handlungsbedarf des
     * Dashboards. Gibt es keinen, fuehrt der Knopf in die Liste: Dort steht
     * dann, warum nichts anzurufen ist.
     *
     * Die Vorauswahl bleibt klein (zehn Zeilen): Wer zehn alte Kaeufe ohne
     * Entscheidung hat, ist nicht mehr der Fall dieser Karte.
     */
    private function oldestOpenPurchaseUrl(Tenant $workspace): string
    {
        $purchase = LeadPurchase::query()
            ->with('lead')
            ->ofBuyer($workspace)
            ->where('status', PurchaseStatus::RESERVED)
            ->oldest()
            ->limit(10)
            ->get()
            ->first(static fn (LeadPurchase $purchase): bool => $purchase->lead?->isOpen() ?? false);

        if (! $purchase instanceof LeadPurchase) {
            return route('portal.leads', ['tenant' => $workspace->uuid]);
        }

        return route('portal.leads.show', [
            'tenant' => $workspace->uuid,
            'purchase' => $purchase->getKey(),
        ]);
    }
}
