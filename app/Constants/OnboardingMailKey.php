<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Die vier Mails der Onboarding-Strecke (LP-ONBOARD-014).
 *
 * Der Case-Wert steht so in der Spalte `key` von `onboarding_mails_sent` und
 * ist damit der halbe Idempotenzschluessel -- er darf nach dem ersten Lauf
 * nicht mehr geaendert werden, sonst bekommt jeder Bestandskunde die Strecke
 * ein zweites Mal.
 *
 * Der Abstand zum Wizard-Abschluss steht hier und nicht im Zuhoerer: Die
 * Strecke ist ein Fahrplan, und ein Fahrplan gehoert an eine Stelle. Der
 * Zuhoerer plant nur, was dieses Enum aufzaehlt.
 */
enum OnboardingMailKey: string
{
    /** Sofort nach dem Wizard: "Du bist startklar". */
    case WELCOME = 'welcome';

    /** Tag 1: wie abgerechnet wird. */
    case BILLING_EXPLAINED = 'billing_explained';

    /** Tag 3: was gerade im Marktplatz auf ihn passt. */
    case LEADS_WAITING = 'leads_waiting';

    /** Tag 7: der letzte Anstupser. */
    case NUDGE = 'nudge';

    /**
     * Wie viele Tage nach dem Wizard diese Mail geplant wird.
     */
    public function delayDays(): int
    {
        return match ($this) {
            self::WELCOME => 0,
            self::BILLING_EXPLAINED => 1,
            self::LEADS_WAITING => 3,
            self::NUDGE => 7,
        };
    }

    /**
     * Der Blade-Rumpf unter resources/views/emails/onboarding/.
     */
    public function view(): string
    {
        return 'emails.onboarding.'.str_replace('_', '-', $this->value);
    }

    /**
     * Der Zweig in den mail.php-Dateien unter lang/.
     */
    public function langKey(): string
    {
        return 'mail.onboarding.'.$this->value;
    }

    /**
     * Das Stueck Adresse der Vorschau: /mail-preview/onboarding-welcome.
     */
    public function previewSlug(): string
    {
        return 'onboarding-'.str_replace('_', '-', $this->value);
    }

    public static function fromPreviewSlug(string $slug): ?self
    {
        foreach (self::cases() as $key) {
            if ($key->previewSlug() === $slug) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $key): string => $key->value, self::cases());
    }
}
