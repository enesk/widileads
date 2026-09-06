{{-- Gemeinsamer Rahmen einer Frage: Beschriftung, Hilfetext, Fehlermeldung. --}}
@props(['question'])

<div class="flex flex-col gap-2">
    <label class="text-sm font-medium" for="{{ $question->fieldKey }}">
        {{ $question->label }}
        @if ($question->required)
            <span class="text-error" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($question->helpText)
        <p class="text-sm text-base-content/60">{{ $question->helpText }}</p>
    @endif

    {{ $slot }}

    @error('answers.' . $question->fieldKey)
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>
