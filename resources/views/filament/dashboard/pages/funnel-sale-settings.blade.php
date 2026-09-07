{{-- FB-055a: Verkaufseinstellungen eines Funnels, mit Filament-Formular. --}}
<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            {{ __('builder.sale.submit') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
