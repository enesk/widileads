{{-- FB-091: Benachrichtigungsadressen eines Funnels, mit Filament-Formular. --}}
<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            {{ __('builder.notifications.submit') }}
        </x-filament::button>
    </form>
</x-filament-panels::page>
