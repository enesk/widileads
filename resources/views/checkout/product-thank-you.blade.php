<x-layouts.focus-center :backButton="false">

    <x-slot name="title">
        @if ($order === null)
            {{ __('marketplace.wallet.top_up.success.unknown_heading') }}
        @else
            {{ $isPending
                ? __('marketplace.wallet.top_up.success.pending_heading')
                : __('marketplace.wallet.top_up.success.heading') }}
        @endif
    </x-slot>

    @php
        $marketplaceUrl = ($order !== null && ($tenant ?? null) !== null)
            ? route('filament.dashboard.pages.marketplace', ['tenant' => $tenant])
            : route('home');

        // Angezeigt wird Geld, gerechnet wird in Cent: Die Betraege kommen als
        // Cent herein und werden hier nur formatiert (LP-WALLET-009).
        $money = static fn (int $cents): string => number_format($cents / 100, 2, ',', '.').' €';
    @endphp

    <div class="mx-auto max-w-lg px-4 py-16 md:py-24">

        @if ($order === null)
            {{-- Kein Fehler, sondern ein Weg zurueck: Wer hier landet, hat einen
                 abgelaufenen Link -- sein Guthaben liegt trotzdem bereit. --}}
            <div class="flex flex-col items-center gap-4 rounded-2xl border border-neutral-200 bg-white p-8 text-center md:p-10">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-500">
                    @svg('heroicon-o-magnifying-glass', 'h-7 w-7')
                </span>

                <h1 class="text-2xl font-bold tracking-tight text-primary-900 md:text-3xl">
                    {{ __('marketplace.wallet.top_up.success.unknown_heading') }}
                </h1>
                <p class="max-w-sm text-neutral-700">
                    {{ __('marketplace.wallet.top_up.success.unknown_text') }}
                </p>

                <a href="{{ $marketplaceUrl }}"
                   class="mt-2 flex min-h-11 w-full items-center justify-center rounded-xl bg-primary-500 px-6 py-3 font-semibold text-white hover:bg-primary-600 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 sm:w-auto">
                    {{ __('To the marketplace') }}
                </a>
            </div>
        @else
            <div class="flex flex-col items-center gap-4 rounded-2xl border border-neutral-200 bg-white p-6 text-center md:p-10">

                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-500">
                    @if ($isPending)
                        @svg('heroicon-o-clock', 'h-7 w-7')
                    @else
                        @svg('heroicon-o-check-circle', 'h-7 w-7')
                    @endif
                </span>

                <h1 class="text-2xl font-bold tracking-tight text-primary-900 md:text-3xl">
                    {{ $isPending
                        ? __('marketplace.wallet.top_up.success.pending_heading')
                        : __('marketplace.wallet.top_up.success.heading') }}
                </h1>

                <p class="max-w-sm text-neutral-700">
                    @if ($isPending)
                        {{ __('marketplace.wallet.top_up.success.pending_text') }}
                    @else
                        {{ __('marketplace.wallet.top_up.success.text', ['amount' => $money($amountCents)]) }}
                    @endif
                </p>

                {{-- Der wichtigste Wert der Seite: was jetzt auf dem Konto steht. --}}
                <div class="mt-2 w-full rounded-xl border border-neutral-200 bg-neutral-50 px-6 py-4 sm:max-w-xs">
                    <div class="text-sm text-neutral-500">
                        {{ __('marketplace.wallet.top_up.success.balance_label') }}
                    </div>
                    <div class="text-3xl font-semibold tabular-nums text-primary-900">
                        {{ $money($balanceCents) }}
                    </div>
                    @if ($isPending && $amountCents > 0)
                        <div class="text-sm text-neutral-500">
                            {{ __('marketplace.wallet.top_up.success.pending_addition', ['amount' => $money($amountCents)]) }}
                        </div>
                    @endif
                </div>

                <div class="mt-2 flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                    <a href="{{ $marketplaceUrl }}" autofocus
                       class="flex min-h-11 items-center justify-center rounded-xl bg-primary-500 px-6 py-3 font-semibold text-white hover:bg-primary-600 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                        {{ __('To the marketplace') }}
                    </a>
                </div>

                <p class="mt-2 text-sm text-neutral-500">
                    {{ __('We will send the invoice to :email.', ['email' => auth()->user()?->email]) }}
                </p>
            </div>
        @endif
    </div>

</x-layouts.focus-center>
