<div>
    {{-- Eine Karte, eine Spalte. Die Anbieterauswahl ist eine Entscheidung des
         Betreibers und keine des Kunden -- sie erscheint nur, wenn wirklich
         mehr als ein Anbieter aktiv ist. --}}
    <form action="" method="post" wire:submit="checkout">
        @csrf

        <div class="rounded-2xl border border-neutral-200 bg-white p-6 md:p-8">
            <div class="flex flex-col gap-6">

                @if ($requiresPayment || ! auth()->check())
                    @include('livewire.checkout.partials.login-or-register')
                @endif

                @include('livewire.checkout.partials.product-details')

                @if ($requiresPayment && count($paymentProviders) > 1)
                    <div>
                        <div class="mb-2 text-sm font-medium text-neutral-700">{{ __('Payment method') }}</div>
                        <div class="inline-flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                            @foreach ($paymentProviders as $provider)
                                <label class="cursor-pointer">
                                    <input type="radio" class="sr-only peer" name="paymentProvider"
                                           value="{{ $provider->getSlug() }}" wire:model="paymentProvider">
                                    <span class="flex min-h-11 items-center rounded-lg px-4 text-sm font-medium text-neutral-600 peer-checked:border peer-checked:border-neutral-200 peer-checked:bg-white peer-checked:text-primary-900">
                                        {{ $provider->getName() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($requiresPayment)
            @foreach ($paymentProviders as $paymentProvider)
                @includeIf('payment-providers.' . $paymentProvider->getSlug())
            @endforeach
        @endif

        {{-- Abschluss steht direkt unter der Karte, nicht in einer fixierten
             Leiste: Der Betrag und der Knopf, der ihn ausloest, gehoeren
             zusammen. Der Betrag im Knopf ist zugleich die Button-Loesung nach
             § 312j BGB. --}}
        <div class="mt-6">
            <button
                type="submit"
                @disabled(! $this->isCheckoutButtonEnabled())
                wire:loading.attr="disabled"
                class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary-500 px-6 py-3 font-semibold text-white transition hover:bg-primary-600 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:opacity-40"
            >
                <span wire:loading.remove>
                    {{ __('Pay :amount now', ['amount' => money($totals->amountDue, $totals->currencyCode)]) }}
                </span>
                <span wire:loading class="flex items-center gap-2">
                    <span class="loading loading-ring loading-xs"></span>
                    {{ __('Redirecting…') }}
                </span>
            </button>

            <p class="mt-3 text-center text-xs leading-relaxed text-neutral-500">
                {!! __('By buying you agree to our :terms and :privacy. Right of withdrawal: :withdrawal.', [
                    'terms' => '<a target="_blank" class="text-primary-500 hover:underline" href="'.route('terms-of-service').'">'.__('Terms of Service').'</a>',
                    'privacy' => '<a target="_blank" class="text-primary-500 hover:underline" href="'.route('privacy-policy').'">'.__('Privacy Policy').'</a>',
                    'withdrawal' => '<a target="_blank" class="text-primary-500 hover:underline" href="'.route('terms-of-service').'">'.__('notes').'</a>',
                ]) !!}
            </p>

            @if ($requiresPayment && count($paymentProviders) > 0)
                <p class="mt-3 flex items-center justify-center gap-2 text-xs text-neutral-400">
                    @svg('heroicon-o-lock-closed', 'h-4 w-4')
                    {{ __('Payment is handled securely by :provider.', ['provider' => $paymentProviders[0]->getName()]) }}
                </p>
            @endif
        </div>
    </form>
</div>
