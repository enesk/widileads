{{--
    Nach der Zahlung (Entwurf `guthaben-erfolg.html`).

    Drei Zustaende in einer schmalen Spalte, immer nur einer sichtbar:

      pending  Zahlung da, Gutschrift laeuft. Die Seite fragt alle zwei
               Sekunden nach (wire:poll).
      done     Die Buchung steht. Guthaben, Rechnung, Weg zum Marktplatz.
      slow     Nach 30 Sekunden immer noch nichts. Kein Fehler, sondern eine
               Ansage: Das Geld ist da, die Buchung kommt.

    Im Entwurf schaltet ein Skript die Zustaende nach 2,5 Sekunden um. Hier
    entscheidet der Server, und zwar an der einzigen ehrlichen Stelle: ob die
    Aufladebuchung im Wallet-Journal steht. Deshalb gibt es kein
    `modules/topup-result.js` -- das Nachfragen erledigt wire:poll.

    Erwartete Daten:
      $state        pending | done | slow
      $amount       aufgeladener Betrag, fertig formatiert (oder null)
      $balance      Guthaben jetzt
      $leadsCovered fuer wie viele Leads es reicht
      $invoiceUrl   Rechnung als PDF (oder null)
      $reference    gekuerzte Zahlungsreferenz (oder null)
      $marketplaceUrl, $walletUrl, $email, $supportEmail
      $shouldPoll   nur solange etwas offen ist
--}}
<div @if ($shouldPoll) wire:poll.2s @endif>
    <div class="max-w-md mx-auto" aria-live="polite">

        @if ($state === 'pending')
            {{-- Zustand 1: wird gutgeschrieben, dauert Sekunden. --}}
            <section class="card p-6 md:p-8">
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center shrink-0">
                        <x-app.icon name="spinner" class="size-8 animate-spin shrink-0" />
                    </span>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-zinc-900">
                            {{ __('marketplace.wallet.top_up.success.pending.heading') }}
                        </h1>
                        <p class="text-zinc-600">{{ __('marketplace.wallet.top_up.success.pending.text') }}</p>
                    </div>
                </div>

                {{-- Was schon passiert ist und was noch laeuft. Die Liste
                     beantwortet die Frage, die ein wartender Mensch stellt:
                     haengt es an mir? --}}
                <ol class="mt-6 space-y-3 pl-1">
                    <li class="flex items-center gap-3 text-sm text-zinc-900">
                        <span class="size-7 rounded-full bg-brand text-white flex items-center justify-center">
                            <x-app.icon name="check" class="size-4 shrink-0" />
                        </span>
                        {{ __('marketplace.wallet.top_up.success.pending.step_paid') }}
                    </li>
                    <li class="flex items-center gap-3 text-sm text-zinc-900 font-medium">
                        <span class="size-7 rounded-full bg-brand-50 text-brand flex items-center justify-center">
                            <x-app.icon name="spinner" class="size-4 animate-spin shrink-0" />
                        </span>
                        {{ __('marketplace.wallet.top_up.success.pending.step_credit') }}
                    </li>
                    <li class="flex items-center gap-3 text-sm text-zinc-400">
                        <span class="size-7 rounded-full border-2 border-zinc-200"></span>
                        {{ __('marketplace.wallet.top_up.success.pending.step_invoice') }}
                    </li>
                </ol>

                @if ($amount !== null)
                    <div class="mt-6 rounded-xl bg-zinc-50 border border-zinc-200 p-4 flex items-center justify-between gap-3">
                        <span class="text-sm text-zinc-500">{{ __('marketplace.wallet.top_up.success.amount_label') }}</span>
                        <span class="font-semibold text-zinc-900 tabular-nums">+{{ $amount }}</span>
                    </div>
                @endif

                <p class="mt-4 text-xs text-zinc-500">{{ __('marketplace.wallet.top_up.success.pending.hint') }}</p>
            </section>

        @elseif ($state === 'done')
            {{-- Zustand 2: fertig. --}}
            <section class="card p-6 md:p-8 text-center">
                <span class="mx-auto size-16 rounded-full bg-brand text-white flex items-center justify-center">
                    <x-app.icon name="check" class="size-8 shrink-0" />
                </span>

                <h1 class="mt-5 text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">
                    {{ __('marketplace.wallet.top_up.success.heading') }}
                </h1>

                @if ($amount !== null)
                    <p class="mt-2 text-zinc-600">{{ __('marketplace.wallet.top_up.success.done_text', ['amount' => $amount]) }}</p>
                @endif

                <div class="mt-6 rounded-xl bg-zinc-50 border border-zinc-200 px-5 py-4">
                    <p class="text-sm text-zinc-500">{{ __('marketplace.wallet.top_up.success.balance_now') }}</p>
                    <p class="text-3xl font-semibold text-zinc-900 tabular-nums">{{ $balance }}</p>
                    <p class="text-xs text-zinc-500 mt-1">
                        {{ trans_choice('marketplace.wallet.top_up.success.covers', $leadsCovered, ['count' => $leadsCovered]) }}
                    </p>
                </div>

                <div class="mt-6 flex flex-col gap-2">
                    <a href="{{ $marketplaceUrl }}" class="btn-primary w-full" autofocus>
                        <x-app.icon name="cart" />
                        {{ __('marketplace.purchased.to_marketplace') }}
                    </a>

                    {{-- Die Rechnung nur, wenn es sie gibt. Ohne
                         Verkaeuferangaben erzeugt der InvoiceService keine,
                         und ein Knopf ins Leere ist schlimmer als keiner. --}}
                    @if ($invoiceUrl !== null)
                        <a href="{{ $invoiceUrl }}" class="btn-secondary w-full">
                            <x-app.icon name="download" />
                            {{ __('marketplace.wallet.top_up.success.invoice') }}
                        </a>
                    @endif
                </div>

                @if ($email !== null)
                    <p class="mt-4 text-xs text-zinc-500 flex items-center justify-center gap-1.5">
                        <x-app.icon name="mail" class="size-4 shrink-0" />
                        {{ __('marketplace.wallet.top_up.success.invoice_mail', ['email' => $email]) }}
                    </p>
                @endif
            </section>

        @else
            {{-- Zustand 3: dauert laenger als erwartet. Kein Fehler -- es gibt
                 nichts, was der Kaeufer richten koennte, und doppelt abgebucht
                 wurde nichts. --}}
            <section class="card p-6 md:p-8 border-amber-200">
                <h1 class="text-2xl font-bold tracking-tight text-zinc-900">
                    {{ __('marketplace.wallet.top_up.success.slow.heading') }}
                </h1>
                <p class="mt-2 text-zinc-600">{{ __('marketplace.wallet.top_up.success.slow.text') }}</p>

                @if ($reference !== null)
                    <div class="mt-5 rounded-xl bg-zinc-50 border border-zinc-200 p-4 text-sm flex items-center justify-between gap-3">
                        <span class="text-zinc-500">{{ __('marketplace.wallet.top_up.success.slow.reference') }}</span>
                        <span class="font-medium text-zinc-900 tabular-nums">{{ $reference }}</span>
                    </div>
                @endif

                <div class="mt-6 flex flex-col gap-2">
                    <a href="{{ $marketplaceUrl }}" class="btn-primary w-full">
                        {{ __('marketplace.purchased.to_marketplace') }}
                    </a>
                    <a href="mailto:{{ $supportEmail }}" class="btn-ghost w-full">
                        {{ __('marketplace.wallet.top_up.success.slow.support') }}
                    </a>
                </div>
            </section>
        @endif

    </div>
</div>
