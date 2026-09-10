<x-layouts.focus-center :backButton="false">

    <x-slot name="title">
        @if ($order === null)
            {{ __('Order not found') }}
        @else
            {{ $isPending ? __('Almost there') : __('Credits topped up') }}
        @endif
    </x-slot>

    @php
        $marketplaceUrl = ($order !== null && ($tenant ?? null) !== null)
            ? route('filament.dashboard.pages.marketplace', ['tenant' => $tenant])
            : route('home');
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
                    {{ __('We do not know this order') }}
                </h1>
                <p class="max-w-sm text-neutral-700">
                    {{ __('The link may have expired. You will find your credits in the marketplace.') }}
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
                    {{ $isPending ? __('Almost there') : __('Credits topped up') }}
                </h1>

                <p class="max-w-sm text-neutral-700">
                    @if ($isPending)
                        {{ __('As soon as the payment is confirmed we will add your credits. For bank transfers this usually takes one to two working days.') }}
                    @else
                        {{ trans_choice(':count lead credits have been added.|:count lead credits have been added.', $credits, ['count' => $credits]) }}
                    @endif
                </p>

                {{-- Der wichtigste Wert der Seite: was jetzt auf dem Konto steht. --}}
                <div class="mt-2 w-full rounded-xl border border-neutral-200 bg-neutral-50 px-6 py-4 sm:max-w-xs">
                    <div class="text-sm text-neutral-500">
                        {{ $isPending ? __('Your credits') : __('Your credits now') }}
                    </div>
                    <div class="text-3xl font-semibold tabular-nums text-primary-900">
                        {{ trans_choice(':count leads|:count leads', $balance, ['count' => $balance]) }}
                    </div>
                    @if ($isPending && $credits > 0)
                        <div class="text-sm text-neutral-500">
                            {{ __('+:count once the payment arrives', ['count' => $credits]) }}
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
