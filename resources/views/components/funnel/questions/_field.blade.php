{{-- Gemeinsamer Rahmen einer Frage: Beschriftung, Hilfetext, Fehlermeldung.

     FB-027: Hilfetext und Fehlermeldung sind ueber aria-describedby mit dem
     Feld verknuepft, damit ein Screenreader sie beim Betreten des Feldes
     vorliest - sichtbar daneben zu stehen genuegt dafuer nicht. Bei
     Auswahlgruppen (Radio, Checkbox) traegt ein fieldset die Frage als legend:
     ein <label for> zeigte dort ins Leere, weil es kein einzelnes Feld gibt.
--}}
@props(['question', 'group' => false])

@php
    $fieldKey = $question->fieldKey;
    $errorKey = 'answers.' . $fieldKey;
    $hasError = isset($errors) && $errors->has($errorKey);
    $describedBy = array_filter([
        $question->helpText ? $fieldKey . '-help' : null,
        $hasError ? $fieldKey . '-error' : null,
    ]);
@endphp

<div class="flex flex-col gap-2">
    @if ($group)
        <fieldset class="flex flex-col gap-2"
                  @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
                  @if ($question->required) aria-required="true" @endif>
            <legend class="text-sm font-medium">
                {{ $question->label }}
                @if ($question->required)
                    <span class="text-error" aria-hidden="true">*</span>
                    <span class="sr-only">{{ __('runtime.required') }}</span>
                @endif
            </legend>

            @if ($question->helpText)
                <p id="{{ $fieldKey }}-help" class="text-sm text-base-content/60">{{ $question->helpText }}</p>
            @endif

            {{ $slot }}
        </fieldset>
    @else
        <label class="text-sm font-medium" for="{{ $fieldKey }}">
            {{ $question->label }}
            @if ($question->required)
                <span class="text-error" aria-hidden="true">*</span>
                <span class="sr-only">{{ __('runtime.required') }}</span>
            @endif
        </label>

        @if ($question->helpText)
            <p id="{{ $fieldKey }}-help" class="text-sm text-base-content/60">{{ $question->helpText }}</p>
        @endif

        {{ $slot }}
    @endif

    @error($errorKey)
        {{-- role="alert" laesst die Meldung ansagen, sobald sie erscheint. --}}
        <p id="{{ $fieldKey }}-error" class="text-sm text-error" role="alert">{{ $message }}</p>
    @enderror
</div>
