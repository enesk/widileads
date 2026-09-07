{{--
    FB-054: Kaufbestaetigung. Kontaktdaten kommen ausschliesslich aus dem
    Presenter -- er liefert sie im Klartext, weil der Kaufbeleg existiert.
    Diese Ansicht entscheidet nichts, sie gibt aus.
--}}
<x-layouts.email>
    <x-slot name="preview">
        {{ __('marketplace.purchase.mail.heading') }}
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                {{ __('marketplace.purchase.mail.heading') }}
            </h1>

            <p style="margin: 0 0 16px; line-height: 24px">
                {{ __('marketplace.purchase.mail.intro', [
                    'funnel' => $lead->funnel?->name ?? __('marketplace.listing.unknown_funnel'),
                ]) }}
            </p>

            <h2 style="margin: 24px 0 12px; font-size: 18px; font-weight: 600; color: #000">
                {{ __('marketplace.purchase.mail.contact_heading') }}
            </h2>

            <p style="margin: 0 0 16px; line-height: 24px">
                <strong>{{ __('funnel.buyer.fields.contact_name') }}:</strong> {{ $presenter->name() }}<br>
                <strong>{{ __('marketplace.listing.email') }}:</strong> {{ $presenter->email() }}<br>
                <strong>{{ __('marketplace.listing.phone') }}:</strong> {{ $presenter->phone() }}<br>
                <strong>{{ __('marketplace.listing.region') }}:</strong> {{ $presenter->postalCode() }}
            </p>

            <p style="margin: 0; line-height: 24px">
                {{ __('marketplace.purchase.mail.outro') }}
            </p>
        </td>
    </tr>
</x-layouts.email>
