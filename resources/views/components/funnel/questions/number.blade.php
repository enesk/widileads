@props(['question'])

<x-funnel.questions._field :question="$question">
    <input type="number" inputmode="numeric" id="{{ $question->fieldKey }}"
           class="input input-bordered min-h-[44px] w-full"
           wire:model="answers.{{ $question->fieldKey }}"
           <x-funnel.questions._aria :question="$question" />>
</x-funnel.questions._field>
