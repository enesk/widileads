{{--
    Das Band ueber jeder Portalseite, solange das Konto gesperrt ist
    (LP-POSTPAID-010).

    Die Sperre entsteht bei einer Zahlungsstoerung und gilt unabhaengig vom
    Zahlungsmodus (App\Exceptions\PurchaseBlockedException). Sie gehoert
    deshalb in den Rahmen und nicht auf eine einzelne Seite: Wer sie nur am
    Marktplatz saehe, wuesste auf der Guthabenseite nicht, warum nichts geht.

    Der Weg heraus steht im Band: die Aufladeseite mit dem offenen Betrag als
    vorbelegtem Betrag. Ein Band ohne Ausweg waere eine Sackgasse.

    Im Ordner components/app wie die uebrigen Portal-Bausteine, obwohl das
    Ticket components/portal nennt -- ein zweiter Namensraum fuer ein einziges
    Element haette nichts geordnet.

    @param \App\Models\Tenant|null $tenant
--}}
@props(['tenant' => null])

@php
    $wallet = $tenant?->isBuyer() ? $tenant->wallet : null;
    $openCents = $wallet?->open_amount_cents ?? 0;
@endphp

@if ($wallet !== null && $wallet->purchase_blocked)
    <div role="alert" {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900']) }}>
        <span class="shrink-0"><x-app.icon name="alert" class="size-5" /></span>

        <span class="flex-1 min-w-0">
            <span class="font-semibold">{{ __('portal.postpaid.blocked.heading') }}</span>
            {{ __('portal.postpaid.blocked.text', ['amount' => \App\Support\Money::format($openCents)]) }}
        </span>

        @if ($openCents > 0)
            <a
                href="{{ route('portal.wallet', ['tenant' => $tenant->uuid, 'betrag' => (int) ceil($openCents / 100)]) }}"
                class="btn-secondary shrink-0"
            >
                {{ __('portal.postpaid.blocked.action') }}
            </a>
        @endif
    </div>
@endif
