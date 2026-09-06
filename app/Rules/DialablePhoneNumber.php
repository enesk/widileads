<?php

declare(strict_types=1);

namespace App\Rules;

use App\Funnel\QuestionTypes\PhoneType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Die Eingabe muss sich als waehlbare Rufnummer lesen lassen (FB-020).
 *
 * Ein Lead mit unbrauchbarer Telefonnummer ist wertlos: Der Kaeufer bezahlt
 * ihn, kann aber niemanden anrufen, und der Anrufnachweis (Phase 2) laeuft ins
 * Leere. Deshalb wird schon beim Absenden geprueft, nicht erst beim Verkauf.
 */
class DialablePhoneNumber implements ValidationRule
{
    public function __construct(private readonly PhoneType $phoneType) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || $this->phoneType->toE164($value) === null) {
            $fail(__('funnel.runtime.errors.phone_not_dialable'))->translate();
        }
    }
}
