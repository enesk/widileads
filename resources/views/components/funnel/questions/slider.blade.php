@props(['question'])

<x-funnel.questions._field :question="$question">
    <input type="range" class="range range-primary min-h-[44px]"
           min="{{ $question->validation['min'] ?? 0 }}"
           max="{{ $question->validation['max'] ?? 10 }}"
           wire:model.live="answers.{{ $question->fieldKey }}"
           <x-funnel.questions._aria :question="$question" />>
    <output class="text-sm text-base-content/70">{{ $question->fieldKey }}: {{ $this->answers[$question->fieldKey] ?? '' }}</output>
</x-funnel.questions._field>
