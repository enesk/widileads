@props(['question'])

<div class="rounded-box bg-base-200 p-4">
    <p class="font-medium">{{ $question->label }}</p>

    @if ($question->helpText)
        <p class="mt-1 text-sm text-base-content/70">{{ $question->helpText }}</p>
    @endif
</div>
