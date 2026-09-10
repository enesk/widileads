@php
    $cartItem = $cartDto->items[0];
@endphp

<div class="flex flex-col gap-6">

    {{-- Produktzeile: was man bekommt, benannt nach dem Nutzen. --}}
    <div class="flex items-start gap-4">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-500">
            @svg('heroicon-o-shopping-cart', 'h-7 w-7')
        </div>

        <div class="min-w-0">
            <div class="text-lg font-semibold text-primary-900">
                {{ $product->name }}
            </div>

            @if ($product->description)
                <p class="text-sm text-neutral-500">{{ $product->description }}</p>
            @endif

            @if (intval($product->max_quantity) === 1)
                <p class="mt-1 text-sm text-neutral-500">
                    {{ __('Quantity:') }} {{ $cartItem->quantity }}
                </p>
            @endif
        </div>
    </div>

    @if ($product->max_quantity == 0 || $product->max_quantity > 1)
        <livewire:checkout.product-quantity :product="$product" />
    @endif

    @inject('tenantCreationService', 'App\Services\TenantCreationService')
    @if ($tenantCreationService->findUserTenantsForNewOrder(auth()->user())->count() > 0)
        <livewire:checkout.product-tenant-picker />
    @endif

    {{-- "Das bekommst du" wird nur gerendert, wenn es etwas zu zeigen gibt --
         ein leerer Abschnitt darf nie erscheinen. --}}
    @if (! empty($product->features))
        <div>
            <div class="text-sm font-medium text-neutral-700">{{ __('What you get') }}</div>
            <ul class="mt-2 flex flex-col gap-2 text-sm text-neutral-700">
                @foreach ($product->features as $feature)
                    <li class="flex items-start gap-2">
                        @svg('check', 'mt-0.5 h-4 w-4 shrink-0 stroke-primary-500')
                        <span>{{ $feature['feature'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <livewire:checkout.product-totals :totals="$totals" :product="$product" page="{{ request()->fullUrl() }}" />
</div>
