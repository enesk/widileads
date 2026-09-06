@props(['question'])

<x-funnel.questions._field :question="$question">
    <input type="email" inputmode="email" autocomplete="email" id="{{ $question->fieldKey }}"
           class="input input-bordered min-h-[44px] w-full"
           wire:model="answers.{{ $question->fieldKey }}">
</x-funnel.questions._field>
