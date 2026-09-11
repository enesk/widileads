<x-layouts.focus-center :backButton="false">

    <x-slot name="title">
        {{ __('marketplace.wallet.top_up.title') }}
    </x-slot>

    {{-- Checkout bleibt ablenkungsfrei: keine Navigation, kein Eyebrow, ein Weg
         zurueck und ein Weg nach vorn. --}}
    <div class="mx-auto max-w-lg px-4 py-10 md:py-16">
        <a href="{{ url()->previous() }}"
           class="-ml-3 mb-2 inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm text-neutral-600 hover:text-primary-900 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
            @svg('heroicon-o-arrow-left', 'h-5 w-5')
            {{ __('Back') }}
        </a>

        <h1 class="text-3xl font-bold tracking-tight text-primary-900 md:text-4xl">
            {{ __('marketplace.wallet.top_up.title') }}
        </h1>
        <p class="mt-2 text-neutral-500">
            {{ __('marketplace.wallet.top_up.checkout_hint') }}
        </p>

        <div class="mt-8">
            <livewire:checkout.product-checkout-form />
        </div>
    </div>

</x-layouts.focus-center>
