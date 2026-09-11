{{-- LP-WALLET-012: Auszahlung anfordern.

     Der Betrag verlaesst das Wallet sofort mit der Anforderung -- deshalb der
     Bestaetigungsdialog vor dem Absenden. Von der Bankverbindung steht hier
     nur die letzte Vierergruppe. --}}
<div class="flex flex-col gap-6">
    <x-filament::section :heading="__('marketplace.wallet.seller.payout.heading')">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.payout.description', [
                    'minimum' => $this->money($minimumCents),
                ]) }}
            </p>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.payout.iban_label') }}:
                <span class="font-medium text-gray-950 dark:text-white">
                    {{ $hasIban ? '•••• '.$ibanLast4 : __('marketplace.wallet.seller.payout.iban_missing') }}
                </span>
            </p>

            @unless ($hasIban)
                <p class="text-sm text-danger-600 dark:text-danger-400">
                    <a href="{{ \App\Filament\Dashboard\Pages\TenantSettings::getUrl() }}" class="underline">
                        {{ __('marketplace.wallet.seller.payout.iban_cta') }}
                    </a>
                </p>
            @endunless

            <div class="max-w-xs">
                <label for="payout_amount" class="block text-sm font-medium text-gray-950 dark:text-white">
                    {{ __('marketplace.wallet.seller.payout.amount_label') }}
                </label>

                <div class="mt-1 flex items-center gap-2">
                    <input
                        id="payout_amount"
                        type="text"
                        inputmode="decimal"
                        wire:model="amountEuro"
                        @disabled(! $canRequest)
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-base text-gray-950 shadow-sm disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    >
                    <span class="text-base text-gray-500 dark:text-gray-400">&euro;</span>
                </div>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('marketplace.wallet.seller.payout.amount_hint', [
                        'available' => $this->money($availableCents),
                        'minimum' => $this->money($minimumCents),
                    ]) }}
                </p>
            </div>

            @if ($hasIban && $availableCents < $minimumCents)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('marketplace.wallet.seller.payout.below_minimum', [
                        'minimum' => $this->money($minimumCents),
                        'available' => $this->money($availableCents),
                    ]) }}
                </p>
            @endif

            <div>
                <x-filament::button
                    wire:click="requestPayout"
                    wire:confirm="{{ __('marketplace.wallet.seller.payout.confirm') }}"
                    wire:loading.attr="disabled"
                    :disabled="! $canRequest"
                >
                    {{ __('marketplace.wallet.seller.payout.submit') }}
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('marketplace.wallet.seller.payout.history_heading')">
        @if ($requests->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('marketplace.wallet.seller.payout.history_empty') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">{{ __('marketplace.wallet.seller.payout.requested_at') }}</th>
                            <th class="py-2 pr-4 text-right font-medium">{{ __('marketplace.wallet.seller.payout.amount') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('marketplace.wallet.seller.payout.iban_label') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('marketplace.wallet.seller.payout.status') }}</th>
                            <th class="py-2 font-medium">{{ __('marketplace.wallet.seller.payout.note') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($requests as $payoutRequest)
                            <tr>
                                <td class="whitespace-nowrap py-2 pr-4 text-gray-500 dark:text-gray-400">
                                    {{ $payoutRequest->requested_at?->translatedFormat('d.m.Y H:i') }}
                                </td>
                                <td class="whitespace-nowrap py-2 pr-4 text-right font-medium tabular-nums text-gray-950 dark:text-white">
                                    {{ $this->money((int) $payoutRequest->amount_cents) }}
                                </td>
                                <td class="whitespace-nowrap py-2 pr-4 text-gray-500 dark:text-gray-400">
                                    {{ $payoutRequest->maskedIban() }}
                                </td>
                                <td class="py-2 pr-4">
                                    <x-filament::badge :color="match ($payoutRequest->status->value) {
                                        'paid' => 'success',
                                        'rejected' => 'danger',
                                        default => 'warning',
                                    }">
                                        {{ $payoutRequest->status->label() }}
                                    </x-filament::badge>
                                </td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">
                                    {{ $payoutRequest->note ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</div>
