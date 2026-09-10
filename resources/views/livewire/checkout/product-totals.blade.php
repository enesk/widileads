<div>
    @php $isDiscountCodeAdded = ! empty($addedCode); @endphp

    {{-- Gutscheincode: eingeklappt und linksbuendig. Ein rechtsbuendiger Link
         wirkt wie ein verirrter Fussnotenverweis. --}}
    <div x-data="{ discountFormVisible: @js($isDiscountCodeAdded) }">
        <a href="#" x-show="! discountFormVisible" x-on:click.prevent="discountFormVisible = true"
           class="text-sm text-primary-500 hover:underline">
            {{ __('Redeem a coupon code') }}
        </a>

        <div x-show="discountFormVisible" x-collapse>
            @if (session('success'))
                <p class="flex items-center gap-2 text-sm text-emerald-700">
                    @svg('check', 'h-4 w-4 stroke-emerald-700')
                    {{ session('success') }}
                </p>
            @endif

            @if (session('error'))
                <p class="text-sm text-red-600">{{ session('error') }}</p>
            @endif

            @if ($isDiscountCodeAdded)
                <div class="mt-2 flex items-center gap-3">
                    <span class="flex items-center gap-2 text-sm text-emerald-700">
                        @svg('check', 'h-4 w-4 stroke-emerald-700')
                        {{ __('Code :code redeemed', ['code' => $addedCode]) }}
                    </span>
                    <a href="#" wire:click.prevent="remove" class="text-sm text-neutral-500 hover:underline">
                        {{ __('Remove') }}
                    </a>
                </div>
            @else
                <div class="mt-2 flex gap-2">
                    <x-input.field wire:model="code" type="text" placeholder="{{ __('Code') }}"
                                   class="input-sm mx-0!" wire:keydown.enter.prevent="add" />
                    <button type="button" wire:click.prevent="add"
                            class="min-h-11 whitespace-nowrap rounded-xl border border-neutral-200 px-4 text-sm font-medium text-primary-900 hover:bg-neutral-50 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                        {{ __('Redeem') }}
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Summe --}}
    <div class="mt-4 border-t border-neutral-200 pt-4">
        <div class="flex items-center justify-between text-sm">
            <span class="text-neutral-500">{{ __('Subtotal') }}</span>
            <span class="tabular-nums text-primary-900">@money($subtotal, $currencyCode)</span>
        </div>

        @if ($discountAmount > 0)
            <div class="mt-2 flex items-center justify-between text-sm">
                <span class="text-neutral-500">
                    {{ $addedCode ? __('Discount (:code)', ['code' => $addedCode]) : __('Discount') }}
                </span>
                <span class="tabular-nums text-emerald-700">-@money($discountAmount, $currencyCode)</span>
            </div>
        @endif

        <div class="mt-3 flex items-center justify-between border-t border-neutral-200 pt-3 text-base font-semibold text-primary-900">
            <span>{{ __('Total') }}</span>
            <span class="tabular-nums">@money($amountDue, $currencyCode)</span>
        </div>

        {{-- Steuerangabe ist Pflicht, nicht Deko. Welcher Satz gilt, steht in
             der Konfiguration -- gerechnet wird die Steuer beim Anbieter. --}}
        <p class="mt-2 text-xs text-neutral-500">
            {{ __(config('app.checkout.vat_note', 'All prices include VAT.')) }}
        </p>
    </div>
</div>
