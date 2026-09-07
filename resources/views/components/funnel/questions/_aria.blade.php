{{-- FB-027: aria-Attribute eines Eingabefeldes.

     Als eigene Komponente, damit jedes Fragetyp-Template dieselbe Verknuepfung
     bekommt und ein neuer Typ sie nicht vergessen kann. Wird innerhalb eines
     Tags eingesetzt: <input ... <x-funnel.questions._aria :question="$question" />>
--}}
@props(['question'])
@php
    $fieldKey = $question->fieldKey;
    $errorKey = 'answers.'.$fieldKey;
    // Ausserhalb einer Anfrage (etwa beim Rendern in Tests) gibt es keine
    // Fehlertasche; das darf die Darstellung nicht scheitern lassen.
    $hasError = isset($errors) && $errors->has($errorKey);
    $describedBy = array_filter([
        $question->helpText ? $fieldKey.'-help' : null,
        $hasError ? $fieldKey.'-error' : null,
    ]);
@endphp
@if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
@if ($hasError) aria-invalid="true" @endif
@if ($question->required) aria-required="true" @endif
