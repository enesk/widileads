{{-- FB-092: Guthaben aufladen. Die Schaltflaeche fuehrt in den vorhandenen
     Einmalkauf-Checkout; bezahlt wird dort ueber Stripe. --}}
<x-filament-panels::page>
    @php
        $balance = $this->balance();
        $unitPrice = $this->unitPrice();
        $packages = $this->packages();
    @endphp

    <x-filament::section>
        <div class="flex flex-wrap items-baseline justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('marketplace.credit.top_up.balance_label') }}
                </p>
                <p class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ __('marketplace.credit.top_up.balance_value', ['credits' => number_format($balance, 0, ',', '.')]) }}
                </p>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('marketplace.credit.top_up.unit_price_hint', [
                    'price' => number_format($unitPrice, 2, ',', '.'),
                ]) }}
            </p>
        </div>
    </x-filament::section>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('marketplace.credit.top_up.description') }}
    </p>

    @if ($packages === [])
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('marketplace.credit.top_up.empty') }}
            </p>
        </x-filament::section>
    @else
        <div class="grid gap-6 md:grid-cols-3">
            @foreach ($packages as $package)
                <x-filament::section>
                    <div class="flex h-full flex-col gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $package['name'] }}
                            </h3>

                            <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">
                                {{ number_format($package['price'], 2, ',', '.') }}&nbsp;&euro;
                            </p>

                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('marketplace.credit.top_up.package_credits', [
                                    'credits' => number_format($package['credits'], 0, ',', '.'),
                                ]) }}
                            </p>
                        </div>

                        @if ($package['description'])
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $package['description'] }}
                            </p>
                        @endif

                        <div class="mt-auto">
                            <x-filament::button
                                tag="a"
                                :href="$package['url']"
                                class="w-full justify-center"
                            >
                                {{ __('marketplace.credit.top_up.buy') }}
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    <p class="text-xs text-gray-400 dark:text-gray-500">
        {{ __('marketplace.credit.top_up.payment_hint') }}
    </p>
</x-filament-panels::page>
