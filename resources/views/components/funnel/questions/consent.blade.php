@props(['question'])

<div class="flex flex-col gap-2">
    <label class="flex min-h-[44px] cursor-pointer items-start gap-3">
        <input type="checkbox" class="checkbox checkbox-primary mt-1"
               wire:model="answers.{{ $question->fieldKey }}" value="1">
        <span class="text-sm">
            {{ $question->label }}
            @if ($question->required)
                <span class="text-error" aria-hidden="true">*</span>
            @endif
        </span>
    </label>

    @error('answers.' . $question->fieldKey)
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>
