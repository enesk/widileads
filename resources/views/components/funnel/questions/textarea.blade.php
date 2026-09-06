@props(['question'])

<x-funnel.questions._field :question="$question">
    <textarea id="{{ $question->fieldKey }}" rows="4" class="textarea textarea-bordered w-full"
              wire:model="answers.{{ $question->fieldKey }}"
           <x-funnel.questions._aria :question="$question" />></textarea>
</x-funnel.questions._field>
