<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

/**
 * E-Mail-Adresse (FB-011). Wird kleingeschrieben gespeichert, damit Dubletten
 * spaeter ueberhaupt auffallen.
 *
 * Bewusst ohne DNS-Pruefung: Sie haengt an einer Netzwerkabfrage mitten im
 * Absenden -- langsam, von fremder Infrastruktur abhaengig und bei einem
 * DNS-Ausfall wuerde die Strecke jede Anfrage abweisen. Die Qualitaetspruefung
 * der Adresse gehoert in die Spam- und Dublettenpruefung (FB-023).
 */
class EmailType extends BaseQuestionType
{
    protected function typeRules(QuestionDefinition $question): array
    {
        return ['string', 'email:rfc', 'max:'.config('funnel.question.text_max_length')];
    }

    public function normalize(mixed $value, QuestionDefinition $question): mixed
    {
        $value = parent::normalize($value, $question);

        return is_string($value) ? mb_strtolower($value) : $value;
    }
}
