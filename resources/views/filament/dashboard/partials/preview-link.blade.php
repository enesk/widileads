{{-- FB-090: Der befristete Vorschau-Link aus FB-018. Er wird nicht gespeichert
     und deshalb nur einmal angezeigt. --}}
<div class="space-y-2">
    <x-filament::input.wrapper>
        <x-filament::input type="text" readonly :value="$link" x-ref="previewLink" />
    </x-filament::input.wrapper>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('builder.funnels.preview_hint') }}
    </p>
</div>
