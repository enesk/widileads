<x-filament-panels::page>
    {{-- FB-080: Bestaetigt wird per Anruf. Der Code steht hier, damit der
         Mitarbeiter hoert, was er erwartet -- eingegeben wird er nirgends. --}}
    @if ($this->callerId())
        <x-filament::section>
            <x-slot name="heading">{{ __('call.caller_id.current_heading') }}</x-slot>

            <div class="space-y-2">
                <p class="text-sm">
                    <span class="font-medium">{{ __('call.caller_id.phone_number') }}:</span>
                    {{ $this->callerId()->phone_number }}
                </p>
                <p class="text-sm">
                    <span class="font-medium">{{ __('call.caller_id.status_label') }}:</span>
                    {{ $this->statusLabel() }}
                </p>

                @if ($this->validationCode())
                    <p class="text-sm">
                        <span class="font-medium">{{ __('call.caller_id.code_label') }}:</span>
                        <span class="font-mono text-lg">{{ $this->validationCode() }}</span>
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('call.caller_id.code_hint') }}
                    </p>
                @endif
            </div>
        </x-filament::section>
    @endif

    <form wire:submit="requestValidation">
        {{ $this->form }}

        <div class="pt-4">
            <x-filament::button type="submit">
                {{ __('call.caller_id.submit') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
