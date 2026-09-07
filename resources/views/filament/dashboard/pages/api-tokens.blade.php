<x-filament-panels::page>
    {{-- FB-090: API-Zugaenge als Filament-Tabelle. Der Klartext eines Tokens
         steht genau einmal hier -- gespeichert ist nur der Hash. --}}
    @if ($plainTextToken !== null)
        <x-filament::section :heading="__('funnel.api_token.plain_text_heading')">
            <p class="text-sm">{{ __('funnel.api_token.plain_text_hint') }}</p>

            <code class="mt-3 block overflow-x-auto rounded-lg bg-gray-50 p-3 font-mono text-sm dark:bg-gray-900"
                  data-testid="plain-text-token">{{ $plainTextToken }}</code>

            <x-slot name="footerActions">
                <x-filament::button wire:click="dismissPlainTextToken" color="gray" size="sm">
                    {{ __('funnel.api_token.plain_text_dismiss') }}
                </x-filament::button>
            </x-slot>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
