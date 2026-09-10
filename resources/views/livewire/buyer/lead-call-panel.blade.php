{{--
    Anrufen und Versuchsverlauf zu einem gekauften Lead (FB-081, FB-084).

    Mobile first: eine Spalte, der Knopf ueber die volle Breite; ab "sm" stehen
    Kopfzeile und Knopf nebeneinander. Angezeigt wird ausschliesslich, was die
    Komponente herausgibt -- ueber Sperrzeit, Zaehler und Rufnummer entscheidet
    nichts in diesem Template.
--}}
<div @if ($this->shouldPoll()) wire:poll.5s="refreshStatus" @endif>
    <x-filament::section>
        <x-slot name="heading">{{ __('call.panel.heading') }}</x-slot>

        <x-slot name="afterHeader">
            @php($badge = $this->badge())
            <x-filament::badge :color="$badge['color']">{{ $badge['label'] }}</x-filament::badge>
        </x-slot>

        <div class="space-y-6">
            {{-- Nummer und Knopf. Ob die Nummer vollstaendig dasteht, entscheidet
                 der LeadContactResolver ueber den Presenter (FB-085). --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('leads.contact.phone') }}
                    </p>
                    <p class="font-mono text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $this->presenter()->phone() }}
                    </p>
                    @if ($hint = $this->presenter()->phoneHint())
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
                    @endif
                </div>

                <div class="w-full sm:w-auto">
                    <x-filament::button
                        wire:click="startCall"
                        wire:loading.attr="disabled"
                        wire:target="startCall"
                        icon="heroicon-o-phone-arrow-up-right"
                        color="primary"
                        size="lg"
                        class="w-full sm:w-auto"
                        :disabled="! $this->canCall()"
                    >
                        {{ __('call.panel.action') }}
                    </x-filament::button>
                </div>
            </div>

            {{-- Waehrend des Aufbaus: Der Mitarbeiter wird zuerst selbst
                 angerufen und muss abnehmen, sonst klingelt es beim Lead nie. --}}
            @if ($calling = $this->callingHint())
                <div class="flex items-start gap-3 rounded-lg bg-primary-50 p-4 text-sm text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                    <x-filament::loading-indicator class="h-5 w-5 shrink-0" />
                    <span>{{ $calling }}</span>
                </div>
            @elseif ($blocked = $this->blockedReason())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $blocked }}</p>
            @endif

            {{-- Das Regelwerk im Klartext, damit der Kaeufer weiss, woran er ist. --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                {{ $this->ruleHint() }}
            </div>

            {{-- Zaehler und Verlauf. --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ __('call.panel.history') }}
                    </h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('call.panel.counter', ['count' => $this->failedCount(), 'required' => $this->requiredAttempts()]) }}
                    </span>
                </div>

                @forelse ($this->attempts() as $attempt)
                    <div class="flex flex-col gap-1 rounded-lg border border-gray-200 p-3 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                        <span class="text-gray-500 dark:text-gray-400">{{ $attempt['when'] }}</span>

                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $attempt['result'] }}</span>

                            @unless ($attempt['counted'])
                                <x-filament::badge color="gray" size="sm">
                                    {{ __('call.panel.not_counted') }}
                                </x-filament::badge>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $attempt['note'] }}</span>
                            @endunless
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('call.panel.no_attempts') }}</p>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</div>
