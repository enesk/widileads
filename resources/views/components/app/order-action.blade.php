{{--
    Der Knopf am Ende einer Bestellzeile. Drei Faelle, drei Knoepfe:

      bezahlt         "Rechnung"            -- nur wenn es eine gibt
      ausstehend      "Zahlung abschliessen" -- fuehrt zur Aufladeseite
      fehlgeschlagen  "Erneut versuchen"     -- ebenfalls dorthin

    Steht je Zeile zweimal im Markup (breite Spalte und die gestapelte Reihe
    fuers Telefon), deshalb hier statt doppelt ausgeschrieben. Auf schmalen
    Bildschirmen tragen die Knoepfe Kurzformen: "Abschliessen", "Erneut", und
    die Rechnung nur ihr Symbol.

    Eine bezahlte Bestellung ohne Rechnung bekommt gar keinen Knopf. Das ist
    kein Fehler: Ohne Verkaeuferangaben erzeugt der InvoiceService keine, und
    ein Knopf ins Leere waere schlimmer als keiner.
--}}
@props(['row', 'topUpUrl'])

@if ($row['tab'] === 'paid')
    @if ($row['invoiceUrl'] !== null)
        <a href="{{ $row['invoiceUrl'] }}" class="btn-secondary min-h-9 px-3 text-sm shrink-0" aria-label="{{ __('marketplace.orders.actions.invoice') }}">
            <x-app.icon name="download" class="size-4 shrink-0" />
            <span class="hidden sm:inline">{{ __('marketplace.orders.actions.invoice') }}</span>
        </a>
    @endif
@elseif ($row['tab'] === 'pending')
    <a href="{{ $topUpUrl }}" class="btn-primary min-h-9 px-3 text-sm shrink-0">
        <span class="sm:hidden">{{ __('marketplace.orders.actions.complete_short') }}</span>
        <span class="hidden sm:inline">{{ __('marketplace.orders.actions.complete') }}</span>
    </a>
@elseif ($row['tab'] === 'failed')
    <a href="{{ $topUpUrl }}" class="btn-secondary min-h-9 px-3 text-sm shrink-0">
        <span class="sm:hidden">{{ __('marketplace.orders.actions.retry_short') }}</span>
        <span class="hidden sm:inline">{{ __('marketplace.orders.actions.retry') }}</span>
    </a>
@endif
