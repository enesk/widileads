@props(['question'])

<x-funnel.questions._field :question="$question">
    <div class="flex flex-col gap-2">
        @foreach ($question->options as $option)
            <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-box border border-base-300 px-4 py-2 hover:bg-base-200">
                <input type="radio" class="radio radio-primary"
                       wire:model="answers.{{ $question->fieldKey }}" value="{{ $option->value }}">
                <span>{{ $option->label }}</span>
            </label>
        @endforeach
    </div>
</x-funnel.questions._field>
