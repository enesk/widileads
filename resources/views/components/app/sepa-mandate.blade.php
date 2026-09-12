{{--
    Der Wortlaut des SEPA-Lastschriftmandats samt Einwilligung
    (LP-POSTPAID-005).

    Der Text steht ueber der Bestaetigung, weil er Inhalt des Mandats ist:
    Ohne ihn weiss der Kaeufer nicht, wem er eine Abbuchungserlaubnis erteilt,
    fuer welche Forderung und dass er acht Wochen Zeit hat, eine Belastung
    zurueckgeben zu lassen. Glaeubiger und Glaeubiger-Identifikationsnummer
    kommen aus config('wallet.postpaid.*') -- beantragt der Betreiber spaeter
    eine eigene Nummer, aendert sich hier nichts.

    Das Haekchen ist Pflicht und heisst `mandate_accepted`; ohne es lehnt
    App\Http\Controllers\Buyer\PaymentMethodController::confirm() ab. Es ist
    absichtlich nicht vorausgewaehlt: Eine vorausgewaehlte Einwilligung ist
    keine.

    Zweiter, unabhaengiger Nachweis ist die Mandatskennung von Stripe. Sie
    entsteht dort nur, wenn der Kaeufer den Mandatstext in Stripe Elements
    bestaetigt hat, und ohne sie speichert der PaymentMethodService nichts.

    @param bool $withConsent  false zeigt nur den Wortlaut, etwa in der
                              Uebersicht eines bereits erteilten Mandats.
--}}
@props(['withConsent' => true])

@php
    $mandate = app(App\Services\Payments\PaymentMethodService::class)->mandate();
@endphp

{{-- wire:model gehoert an das Haekchen und nicht an den Rahmen, deshalb hier ausgenommen. --}}
<div {{ $attributes->except('wire:model')->merge(['class' => 'rounded-xl border border-base-300 bg-base-200/50 p-4 text-sm']) }}>
    <p class="font-semibold text-base-content">
        {{ __('marketplace.wallet.payment_methods.mandate.heading') }}
    </p>

    <dl class="mt-3 grid gap-1 text-xs text-base-content/70 sm:grid-cols-[auto_1fr] sm:gap-x-3">
        <dt>{{ __('marketplace.wallet.payment_methods.mandate.creditor_label') }}</dt>
        <dd class="font-medium text-base-content">{{ $mandate['creditor_name'] }}</dd>
        <dt>{{ __('marketplace.wallet.payment_methods.mandate.creditor_id_label') }}</dt>
        <dd class="font-medium text-base-content">{{ $mandate['creditor_id'] }}</dd>
    </dl>

    <p class="mt-3 leading-relaxed text-base-content/80">
        {{ $mandate['text'] }}
    </p>

    <p class="mt-2 text-xs text-base-content/60">
        {{ __('marketplace.wallet.payment_methods.mandate.prenotification_hint') }}
    </p>

    @if ($withConsent)
        <label class="mt-4 flex items-start gap-3">
            <input
                type="checkbox"
                name="mandate_accepted"
                value="1"
                required
                class="checkbox checkbox-sm mt-0.5 shrink-0"
                @if ($attributes->has('wire:model')) {{ $attributes->only('wire:model') }} @endif
            />
            <span class="text-base-content">
                {{ __('marketplace.wallet.payment_methods.mandate.accept') }}
            </span>
        </label>
    @endif
</div>
