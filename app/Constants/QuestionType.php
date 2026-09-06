<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Fragetypen eines Funnels (FB-011).
 *
 * Der Wert eines Case steht in funnel_questions.type. Zu jedem Case gehoert
 * genau eine Handler-Klasse in app/Funnel/QuestionTypes, die Validierungsregeln,
 * Normalisierung und Blade-Komponente kennt; aufgeloest wird sie ueber die
 * QuestionTypeRegistry nach Namenskonvention (single_choice -> SingleChoiceType).
 * Ein neuer Fragetyp braucht deshalb nur einen Case und eine Klasse -- keine
 * bestehende Verzweigung muss angefasst werden.
 */
enum QuestionType: string
{
    case SINGLE_CHOICE = 'single_choice';

    case MULTI_CHOICE = 'multi_choice';

    case TEXT = 'text';

    case TEXTAREA = 'textarea';

    case NUMBER = 'number';

    case EMAIL = 'email';

    case PHONE = 'phone';

    case DATE = 'date';

    case POSTAL_CODE = 'postal_code';

    case IMAGE_CHOICE = 'image_choice';

    case SLIDER = 'slider';

    case CONSENT = 'consent';

    /** Reiner Textschritt ohne Antwort (Hinweis, Zwischenueberschrift). */
    case INFO = 'info';

    public function label(): string
    {
        return __('funnel.question_type.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
