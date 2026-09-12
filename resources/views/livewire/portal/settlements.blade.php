{{--
    Die Seite "Abrechnungen" im Portal (LP-POSTPAID-010).

    Auf dem Telefon ist jede Abrechnung eine Karte, ab md eine Tabellenzeile --
    vier Spalten nebeneinander sind auf 375 px nicht lesbar. Der Beleg haengt an
    x-app.settlement-invoice-link und fehlt, solange keiner entstanden ist.

    Erwartete Daten: $settlements (Paginator), $walletUrl
--}}
<div>

    <a href="{{ $walletUrl }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline">
        <x-app.icon name="arrow-left" class="size-4" />{{ __('portal.topup.back') }}
    </a>

    <div class="mt-3">
        <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.postpaid.settlements.heading') }}</h1>
        <p class="text-zinc-500 mt-1 max-w-prose">{{ __('portal.postpaid.settlements.description') }}</p>
    </div>

    @if ($settlements->isEmpty())

        <x-app.empty-state
            class="mt-6"
            icon="wallet"
            :title="__('portal.postpaid.settlements.empty_title')"
            :description="__('portal.postpaid.settlements.empty_description')"
        />

    @else

        {{-- Telefon: je Abrechnung eine Karte. --}}
        <ul class="mt-6 space-y-3 md:hidden">
            @foreach ($settlements as $settlement)
                <li>
                    <x-app.card class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-lg font-semibold text-zinc-900 tabular-nums">{{ $this->money($settlement->amount_cents) }}</p>
                                <p class="text-sm text-zinc-500">{{ $settlement->created_at?->format('d.m.Y') }}</p>
                            </div>
                            <span class="badge-tone badge-{{ $this->statusTone($settlement) }} shrink-0">{{ $this->statusLabel($settlement) }}</span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm text-zinc-500 truncate">{{ $this->methodLabel($settlement) }}</p>
                            <x-app.settlement-invoice-link :settlement="$settlement" />
                        </div>
                    </x-app.card>
                </li>
            @endforeach
        </ul>

        {{-- Ab md: eine Tabelle. --}}
        <x-app.card class="mt-6 hidden md:block overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-zinc-500 border-b border-zinc-200">
                        <th scope="col" class="px-5 py-3 font-medium">{{ __('portal.postpaid.settlements.date') }}</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">{{ __('portal.postpaid.settlements.amount') }}</th>
                        <th scope="col" class="px-5 py-3 font-medium">{{ __('portal.postpaid.settlements.method') }}</th>
                        <th scope="col" class="px-5 py-3 font-medium">{{ __('portal.postpaid.settlements.status') }}</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right">{{ __('portal.postpaid.settlements.invoice') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @foreach ($settlements as $settlement)
                        <tr>
                            <td class="px-5 py-3 text-zinc-700 whitespace-nowrap">{{ $settlement->created_at?->format('d.m.Y') }}</td>
                            <td class="px-5 py-3 text-zinc-900 font-medium tabular-nums text-right whitespace-nowrap">{{ $this->money($settlement->amount_cents) }}</td>
                            <td class="px-5 py-3 text-zinc-700">{{ $this->methodLabel($settlement) }}</td>
                            <td class="px-5 py-3">
                                <span class="badge-tone badge-{{ $this->statusTone($settlement) }}">{{ $this->statusLabel($settlement) }}</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <x-app.settlement-invoice-link :settlement="$settlement" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-app.card>

        <x-app.pagination :paginator="$settlements" :wire="true" class="mt-6" />

    @endif

</div>
