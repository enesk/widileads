<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Die Schritte des Kaeufer-Onboardings (LP-ONBOARD-002).
 *
 * Der Case-Wert steht so in der Spalte `step` von `tenant_onboarding_steps`
 * und darf nach dem ersten Einsatz nicht mehr geaendert werden -- sonst sieht
 * ein Bestandskunde den Wizard wieder, weil sein Haken nicht mehr gefunden
 * wird.
 *
 * Zwei Gruppen, und die Unterscheidung ist die ganze Fachlogik dieses Enums:
 * die Pflichtschritte des Wizards (wizardSteps(), in genau dieser Reihenfolge)
 * halten den Kaeufer auf, bis er kaufen kann; die Aktivierungsschritte
 * (activationSteps()) kann er nur noch selbst im Betrieb erledigen und sie
 * stehen als Checkliste im Dashboard. Wer einen neuen Schritt hinzufuegt,
 * entscheidet mit der Gruppe, ob er sperrt oder nur erinnert.
 */
enum OnboardingStep: string
{
    /**
     * Rufnummer bestaetigt. Liegt als Wahrheit in `users.phone_number_verified_at`
     * -- also pro Benutzer und nicht pro Workspace (LP-ONBOARD-003). Der
     * Schritt wird hier nur gespiegelt, damit die Checkliste und der
     * Admin-Trichter alle Schritte aus einer Tabelle lesen koennen.
     */
    case PHONE_VERIFIED = 'phone_verified';

    /** Wizard 1: Branchen gewaehlt. */
    case INDUSTRIES_SELECTED = 'industries_selected';

    /** Wizard 2: Region und Umkreis gewaehlt. */
    case REGION_SELECTED = 'region_selected';

    /** Wizard 3: Zahlungsweise eingerichtet (Guthaben oder Pay as you go). */
    case PAYMENT_SETUP = 'payment_setup';

    /** Erstes Guthaben aufgeladen. Bei Pay as you go faellt er nicht weg, er bleibt nur offen. */
    case FIRST_TOPUP = 'first_topup';

    /** Erster Lead gekauft. */
    case FIRST_PURCHASE = 'first_purchase';

    /**
     * Erster Anruf bei einem gekauften Lead. Das ist der Punkt, an dem ein
     * Kaeufer als aktiviert gilt (isActivated): vorher hat er bezahlt, ab hier
     * arbeitet er.
     */
    case FIRST_CALL = 'first_call';

    /** Tour auf dem Marktplatz gesehen. */
    case TOUR_MARKETPLACE_SEEN = 'tour_marketplace_seen';

    /** Tour im Lead-Detail gesehen. */
    case TOUR_LEAD_DETAIL_SEEN = 'tour_lead_detail_seen';

    /**
     * Die Pflichtschritte des Wizards in der Reihenfolge, in der sie
     * abgefragt werden.
     *
     * @return list<self>
     */
    public static function wizardSteps(): array
    {
        return [
            self::INDUSTRIES_SELECTED,
            self::REGION_SELECTED,
            self::PAYMENT_SETUP,
        ];
    }

    /**
     * Alles, was nach dem Wizard kommt -- die Checkliste im Dashboard.
     *
     * Die Rufnummer gehoert nicht dazu: sie ist eine harte Sperre vor jedem
     * Screen (LP-ONBOARD-003) und keine Erinnerung.
     *
     * @return list<self>
     */
    public static function activationSteps(): array
    {
        return [
            self::FIRST_TOPUP,
            self::FIRST_PURCHASE,
            self::FIRST_CALL,
            self::TOUR_MARKETPLACE_SEEN,
            self::TOUR_LEAD_DETAIL_SEEN,
        ];
    }

    /**
     * Haelt dieser Schritt den Wizard auf?
     */
    public function isWizardStep(): bool
    {
        return in_array($this, self::wizardSteps(), strict: true);
    }

    /**
     * Steht dieser Schritt in der Aktivierungs-Checkliste?
     */
    public function isActivationStep(): bool
    {
        return in_array($this, self::activationSteps(), strict: true);
    }

    /**
     * Das Stueck Adresse, unter dem dieser Schritt im Wizard steht
     * (LP-ONBOARD-007) -- oder null, wenn er keine eigene Seite hat.
     *
     * Die Zuordnung steht hier und nicht in der Routendatei oder der Huelle,
     * weil drei Stellen sie brauchen: die Route selbst, die Gate-Middleware
     * (sie baut daraus das Umleitungsziel) und die Schritt-Leiste. Liefen sie
     * auseinander, leitete die Sperre auf eine Adresse, die es nicht gibt.
     */
    public function wizardSlug(): ?string
    {
        return match ($this) {
            self::INDUSTRIES_SELECTED => 'industries',
            self::REGION_SELECTED => 'region',
            self::PAYMENT_SETUP => 'payment',
            default => null,
        };
    }

    /**
     * Der Wizard-Schritt zu einem Stueck Adresse, oder null bei unbekanntem.
     */
    public static function fromWizardSlug(string $slug): ?self
    {
        foreach (self::wizardSteps() as $step) {
            if ($step->wizardSlug() === $slug) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Die Adressstuecke der Pflichtschritte, in ihrer Reihenfolge.
     *
     * Die Route schraenkt ihren Parameter damit ein -- ein unbekannter Schritt
     * endet in 404 und nicht in einer leeren Huelle.
     *
     * @return list<string>
     */
    public static function wizardSlugs(): array
    {
        return array_values(array_filter(array_map(
            static fn (self $step): ?string => $step->wizardSlug(),
            self::wizardSteps(),
        )));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $step): string => $step->value, self::cases());
    }
}
