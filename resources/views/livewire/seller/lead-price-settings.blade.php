{{-- LP-WALLET-012: Leadpreis des Verkaeufers. Vorschau und Hinweis stehen als
     Hilfetext unter dem Feld, damit beides mitwandert, wenn der Preis sich
     aendert. --}}
<div>
    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit">
                {{ __('marketplace.wallet.seller.price.submit') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
