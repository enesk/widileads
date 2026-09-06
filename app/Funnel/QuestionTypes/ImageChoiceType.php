<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

/**
 * Auswahl ueber Bilder (FB-011) -- fachlich eine Einfachauswahl, nur anders
 * dargestellt. Regeln und Normalisierung erbt der Typ deshalb unveraendert;
 * eigenstaendig ist allein die Blade-Komponente (funnel.questions.image_choice).
 */
class ImageChoiceType extends SingleChoiceType {}
