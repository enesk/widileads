{{--
    Offener Betrag und Kreditrahmen eines Postpaid-Kaeufers (LP-POSTPAID-010).

    Zwei Zahlen statt einer: was offen ist und was noch geht. Der Balken zeigt
    den ausgeschoepften Teil des Rahmens; der Hinweis darunter nennt den
    Einzugstermin, damit der offene Betrag kein Raetsel bleibt.

    Erwartete Daten:
      $visible, $openAmount, $available, $limit, $usedPercent, $exhausted,
      $tooltip, $settlementsUrl
--}}
{{-- Livewire braucht genau ein Wurzelelement; bei Prepaid bleibt es leer. --}}
<div>
    @if ($visible)
        <x-app.card class="p-5 space-y-4">

            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-zinc-500 flex items-center gap-1.5">
                        {{ __('portal.postpaid.balance.open_label') }}
                        <span class="tip" tabindex="0" role="note" aria-label="{{ $tooltip }}" data-tip="{{ $tooltip }}">
                            <x-app.icon name="info" class="size-4 text-zinc-400" />
                        </span>
                    </p>
                    <p class="text-2xl font-semibold text-zinc-900 tabular-nums whitespace-nowrap">{{ $openAmount }}</p>
                </div>

                <div class="min-w-0 sm:text-right">
                    <p class="text-sm text-zinc-500">{{ __('portal.postpaid.balance.remaining_label') }}</p>
                    <p class="text-lg font-semibold text-zinc-900 tabular-nums whitespace-nowrap">
                        {{ __('portal.postpaid.balance.remaining_value', ['available' => $available, 'limit' => $limit]) }}
                    </p>
                </div>
            </div>

            <div
                class="credit-bar"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $usedPercent }}"
                aria-label="{{ __('portal.postpaid.balance.remaining_label') }}"
            >
                <span @class(['credit-bar-fill', 'credit-bar-full' => $exhausted]) style="width: {{ $usedPercent }}%"></span>
            </div>

            @if ($exhausted)
                <p class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-xl p-3">
                    {{ __('portal.postpaid.balance.exhausted') }}
                </p>
            @endif

            <a href="{{ $settlementsUrl }}" class="inline-flex items-center gap-1.5 text-sm text-brand hover:underline">
                {{ __('portal.postpaid.balance.settlements_link') }}
                <x-app.icon name="chevron-right" class="size-4" />
            </a>

        </x-app.card>
    @endif
</div>
