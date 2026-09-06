<div>
    @if ($plainTextToken !== null)
        <div class="mb-6 rounded-xl border border-warning-300 bg-warning-50 p-4 dark:border-warning-600 dark:bg-warning-950">
            <h3 class="text-sm font-semibold text-warning-800 dark:text-warning-200">
                {{ __('funnel.api_token.plain_text_heading') }}
            </h3>
            <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                {{ __('funnel.api_token.plain_text_hint') }}
            </p>
            <code class="mt-3 block overflow-x-auto rounded-lg bg-white p-3 font-mono text-sm text-gray-900 dark:bg-gray-900 dark:text-gray-100">{{ $plainTextToken }}</code>
            <x-filament::button class="mt-3" size="sm" color="gray" wire:click="dismissPlainTextToken">
                {{ __('funnel.api_token.plain_text_dismiss') }}
            </x-filament::button>
        </div>
    @endif

    {{ $this->table }}
</div>
