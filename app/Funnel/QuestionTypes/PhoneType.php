<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Models\FunnelQuestion;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Telefonnummer (FB-011).
 *
 * Gespeichert wird ausschliesslich E.164 ("+4915112345678"), egal wie der
 * Endkunde sie eingegeben hat -- mit Nullen, Leerzeichen, Klammern oder
 * Landesvorwahl. Nur so sind Nummern spaeter vergleichbar (Dublettenpruefung)
 * und waehlbar (Anrufnachweis in Phase 2).
 *
 * Nummern ohne Landesvorwahl werden gegen die Region aus
 * config('funnel.question.default_phone_region') gelesen.
 */
class PhoneType extends BaseQuestionType
{
    public function __construct(private readonly PhoneNumberUtil $phoneNumberUtil) {}

    protected function typeRules(FunnelQuestion $question): array
    {
        return ['string', 'max:'.config('funnel.question.text_max_length')];
    }

    public function normalize(mixed $value, FunnelQuestion $question): mixed
    {
        $value = parent::normalize($value, $question);

        if (! is_string($value)) {
            return $value;
        }

        return $this->toE164($value) ?? $value;
    }

    /**
     * E.164-Schreibweise einer Nummer, oder null, wenn sie sich nicht als
     * gueltige Rufnummer lesen laesst.
     */
    public function toE164(string $number, ?string $region = null): ?string
    {
        $region ??= (string) config('funnel.question.default_phone_region');

        try {
            $parsed = $this->phoneNumberUtil->parse($number, $region);
        } catch (NumberParseException) {
            return null;
        }

        if (! $this->phoneNumberUtil->isValidNumber($parsed)) {
            return null;
        }

        return $this->phoneNumberUtil->format($parsed, PhoneNumberFormat::E164);
    }
}
