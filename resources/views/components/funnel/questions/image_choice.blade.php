@props(['question'])

<x-funnel.questions._field :question="$question">
    <div class="grid grid-cols-2 gap-3">
        @foreach ($question->options as $option)
            <label class="flex min-h-[44px] cursor-pointer flex-col items-center gap-2 rounded-box border border-base-300 p-3 hover:bg-base-200">
                @if ($option->imagePath)
                    <img src="{{ $option->imagePath }}" alt="{{ $option->label }}" class="h-24 w-full rounded object-cover">
                @endif
                <input type="radio" class="radio radio-primary"
                       wire:model="answers.{{ $question->fieldKey }}" value="{{ $option->value }}">
                <span class="text-center text-sm">{{ $option->label }}</span>
            </label>
        @endforeach
    </div>
</x-funnel.questions._field>
