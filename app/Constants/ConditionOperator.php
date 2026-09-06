<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Vergleichsoperator einer Funnel-Verzweigungsregel (FB-010).
 *
 * Das Enum legt ausschliesslich die erlaubten Werte fest. Was ein Operator
 * fachlich prueft, entscheidet der StepResolver in FB-012 -- hier steht bewusst
 * keine Auswertungslogik.
 */
enum ConditionOperator: string
{
    /** Antwort entspricht dem Wert. */
    case EQUALS = 'equals';

    /** Antwort entspricht dem Wert nicht. */
    case NOT_EQUALS = 'not_equals';

    /** Antwort ist in der Werteliste enthalten. */
    case IN = 'in';

    /** Antwort ist groesser als der Wert. */
    case GT = 'gt';

    /** Antwort ist kleiner als der Wert. */
    case LT = 'lt';

    /** Antwort enthaelt den Wert. */
    case CONTAINS = 'contains';

    /** Die Frage wurde ueberhaupt beantwortet (ohne Wertvergleich). */
    case ANSWERED = 'answered';

    /** Die bis hierhin erreichte Punktzahl ist mindestens so hoch wie der Wert. */
    case SCORE_GTE = 'score_gte';

    public function label(): string
    {
        return __('funnel.condition_operator.'.$this->value);
    }

    /**
     * Beschriftungen aller Operatoren, z. B. fuer Auswahlfelder im Builder.
     *
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
