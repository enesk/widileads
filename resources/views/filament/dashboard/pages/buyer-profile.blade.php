<x-filament-panels::page>
    {{-- FB-090: Kaufkriterien als Filament-Formular. Was hier eingegeben wird,
         liest spaeter der LeadMatcher -- entschieden wird dort, nicht hier. --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="pt-4">
            <x-filament::button type="submit">
                {{ __('marketplace.profile.submit') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
