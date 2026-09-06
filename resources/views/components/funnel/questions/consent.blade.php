{{-- Einwilligung: Beschriftung und Feld gehoeren hier zusammen, deshalb kein
     gemeinsamer Rahmen. FB-027: Fehlermeldung ueber aria-describedby verknuepft
     und mit role="alert" ansagbar. --}}
@props(['question'])

@php
    $fieldKey = $question->fieldKey;
    $errorKey = 'answers.' . $fieldKey;
    $hasError = isset($errors) && $errors->has($errorKey);
@endphp

<div class="flex flex-col gap-2">
    <label class="flex min-h-[44px] cursor-pointer items-start gap-3" for="{{ $fieldKey }}">
        <input type="checkbox" id="{{ $fieldKey }}" class="checkbox checkbox-primary mt-1"
               wire:model="answers.{{ $fieldKey }}" value="1"
               @if ($hasError) aria-invalid="true" aria-describedby="{{ $fieldKey }}-error" @endif
               @if ($question->required) aria-required="true" @endif>
        <span class="text-sm">
            {{ $question->label }}
            @if ($question->required)
                <span class="text-error" aria-hidden="true">*</span>
                <span class="sr-only">{{ __('runtime.required') }}</span>
            @endif
        </span>
    </label>

    @error($errorKey)
        <p id="{{ $fieldKey }}-error" class="text-sm text-error" role="alert">{{ $message }}</p>
    @enderror
</div>
