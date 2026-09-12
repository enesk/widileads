{{--
    Der Verweis auf den Beleg einer Postpaid-Abrechnung (LP-POSTPAID-015).

    Gedacht fuer die Abrechnungsliste im Kaeuferportal (LP-POSTPAID-010): eine
    Zeile, ein Knopf. Erwartet wird das Settlement selbst, damit die Liste
    nichts ueber Belegnummern und Dateipfade wissen muss.

    Gibt es keinen Beleg -- der Einzug laeuft noch, oder die eigenen
    Firmenstammdaten fehlen und es entsteht keiner --, steht hier nichts. Ein
    Knopf ins Leere waere schlimmer als keiner; dasselbe gilt bei den
    Bestellungen (x-app.order-action).
--}}
@props(['settlement'])

@if ($settlement->invoice_reference !== null)
    <a
        href="{{ route('buyer.settlements.invoice', ['settlement' => $settlement]) }}"
        class="btn-secondary min-h-9 px-3 text-sm shrink-0"
        aria-label="{{ __('marketplace.wallet.settlement_invoice.action', ['reference' => $settlement->invoice_reference]) }}"
    >
        <x-app.icon name="download" class="size-4 shrink-0" />
        <span class="hidden sm:inline">{{ __('marketplace.wallet.settlement_invoice.link') }}</span>
    </a>
@endif
