{{--
    Die Seite "Zahlungsmittel" im Portal (LP-POSTPAID-010).

    Zwei Teile mit getrennter Zustaendigkeit:

    - Die Liste gehoert Livewire. Sie zeigt die einsatzbereiten Mittel mit
      Typ-Symbol, den letzten vier Stellen, dem Mandatsdatum und der
      Standard-Kennzeichnung; "Entfernen" laeuft ueber die Komponente und
      bringt den Hinweis mit, wenn das Mittel bleiben muss.

    - Der Stepper gehoert dem Browser (`wire:ignore`). IBAN und Kartennummer
      duerfen diese Anwendung nicht beruehren: Sie entstehen in Stripe Elements
      und gehen von dort direkt an Stripe. Die Schritte bedient
      resources/js/modules/payment-method.js ueber die data-Attribute; im
      Markup steht kein onclick.

    Erwartete Daten:
      $tenant, $methods, $types (value, label, hint, icon), $mandate,
      $walletUrl, $isPostpaid
--}}
<div>

    <a href="{{ $walletUrl }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline">
        <x-app.icon name="arrow-left" class="size-4" />{{ __('portal.topup.back') }}
    </a>

    <div class="mt-3">
        <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.postpaid.payment_methods.heading') }}</h1>
        <p class="text-zinc-500 mt-1 max-w-prose">{{ __('portal.postpaid.payment_methods.description') }}</p>
    </div>

    @if ($removalNotice !== null)
        <div role="status" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ $removalNotice }}
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem] items-start">

        <div class="min-w-0 space-y-4">
            @if ($methods->isEmpty())
                <x-app.empty-state
                    icon="card"
                    :title="__('portal.postpaid.payment_methods.empty_title')"
                    :description="__('portal.postpaid.payment_methods.empty_description')"
                />
            @else
                <ul class="space-y-3">
                    @foreach ($methods as $method)
                        <li>
                            <x-app.card class="p-4 flex items-center gap-4">
                                <span class="size-11 rounded-xl bg-zinc-100 text-zinc-500 flex items-center justify-center shrink-0">
                                    <x-app.icon :name="$method->type === App\Constants\PaymentMethodType::CARD ? 'card' : 'bank'" class="size-5" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-zinc-900 truncate">{{ $method->label() }}</p>
                                    @if ($method->mandate_accepted_at !== null)
                                        <p class="text-sm text-zinc-500 truncate">
                                            {{ __('portal.postpaid.payment_methods.mandate_since', ['date' => $method->mandate_accepted_at->format('d.m.Y')]) }}
                                        </p>
                                    @endif
                                </div>

                                @if ($method->is_default)
                                    <x-app.badge variant="brand" class="shrink-0">{{ __('portal.postpaid.payment_methods.default_badge') }}</x-app.badge>
                                @endif

                                <button
                                    type="button"
                                    class="btn-ghost min-h-10 px-3 shrink-0 text-zinc-500"
                                    wire:click="remove({{ $method->getKey() }})"
                                    wire:confirm="{{ __('portal.postpaid.payment_methods.remove_confirm') }}"
                                    wire:loading.attr="disabled"
                                >
                                    <x-app.icon name="trash" class="size-4 shrink-0" />
                                    <span class="sr-only sm:not-sr-only">{{ __('portal.postpaid.payment_methods.remove') }}</span>
                                </button>
                            </x-app.card>
                        </li>
                    @endforeach
                </ul>
            @endif

            <button type="button" class="btn-primary w-full sm:w-auto" data-pm-open>
                <x-app.icon name="plus" />
                {{ __('portal.postpaid.payment_methods.add') }}
            </button>
        </div>

        {{--
            Der Stepper. Livewire fasst ihn nicht an: Stripe Elements haengt in
            einem eigenen iframe, den ein Neuzeichnen der Komponente zerstoeren
            wuerde.
        --}}
        <div
            wire:ignore
            class="hidden"
            data-payment-method-form
            data-setup-url="{{ route('buyer.payment-methods.setup-intent') }}"
            data-confirm-url="{{ route('buyer.payment-methods.confirm') }}"
            data-tenant="{{ $tenant->uuid }}"
            data-return-url="{{ route('portal.payment-methods', ['tenant' => $tenant->uuid]) }}"
            data-locale="{{ app()->getLocale() }}"
            data-done-template="{{ __('portal.postpaid.payment_methods.done_text', ['method' => ':method']) }}"
            data-unavailable="{{ __('portal.postpaid.payment_methods.unavailable') }}"
            data-mandate-required="{{ __('marketplace.wallet.payment_methods.errors.mandate_required') }}"
        >
            <x-app.card class="p-5 space-y-5">

                <ol class="pm-steps" aria-label="{{ __('portal.postpaid.payment_methods.add') }}">
                    @foreach (['type', 'details', 'mandate', 'done'] as $index => $step)
                        <li class="pm-step" data-pm-marker="{{ $index + 1 }}">
                            <span class="pm-step-dot">{{ $index + 1 }}</span>
                            <span class="pm-step-label">{{ __('portal.postpaid.payment_methods.steps.'.$step) }}</span>
                        </li>
                    @endforeach
                </ol>

                {{-- Schritt 1: Art waehlen. Keine ist vorausgewaehlt. --}}
                <div data-pm-step="1">
                    <h2 class="text-base font-semibold text-zinc-900">{{ __('portal.postpaid.payment_methods.type_heading') }}</h2>

                    <div class="mt-3 space-y-2">
                        @foreach ($types as $type)
                            <label class="pkg flex-row items-center justify-between text-left">
                                <input type="radio" name="pm_type" value="{{ $type['value'] }}" class="sr-only">
                                <span class="flex items-center gap-3 min-w-0">
                                    <x-app.icon :name="$type['icon']" class="size-5 text-zinc-500 shrink-0" />
                                    <span class="flex flex-col min-w-0">
                                        <span class="font-medium text-zinc-900">{{ $type['label'] }}</span>
                                        <span class="text-sm text-zinc-500">{{ $type['hint'] }}</span>
                                    </span>
                                </span>
                                <span class="size-5 rounded-full border border-zinc-300 shrink-0 pkg-dot"></span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="button" class="btn-ghost flex-1" data-pm-close>{{ __('portal.postpaid.payment_methods.cancel') }}</button>
                        <button type="button" class="btn-primary flex-1" data-pm-next="1">{{ __('portal.postpaid.payment_methods.next') }}</button>
                    </div>
                </div>

                {{-- Schritt 2: Eingabe bei Stripe. --}}
                <div data-pm-step="2" hidden>
                    <h2 class="text-base font-semibold text-zinc-900">{{ __('portal.postpaid.payment_methods.details_heading') }}</h2>
                    <p class="text-sm text-zinc-500 mt-1">{{ __('portal.postpaid.payment_methods.details_hint') }}</p>

                    <div id="pm-element" class="mt-4 min-h-24"></div>

                    <div class="mt-4 flex gap-2">
                        <button type="button" class="btn-ghost flex-1" data-pm-back="2">{{ __('portal.postpaid.payment_methods.back') }}</button>
                        <button type="button" class="btn-primary flex-1" data-pm-next="2">{{ __('portal.postpaid.payment_methods.next') }}</button>
                    </div>
                </div>

                {{--
                    Schritt 3: Mandatswortlaut mit Pflichthaekchen. Nur bei
                    Lastschrift -- bei Karte gibt es kein Mandat, das fehlen
                    koennte.
                --}}
                <div data-pm-step="3" hidden>
                    <h2 class="text-base font-semibold text-zinc-900">{{ __('portal.postpaid.payment_methods.mandate_heading') }}</h2>

                    <div class="mt-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm">
                        <dl class="grid gap-1 text-xs text-zinc-500 sm:grid-cols-[auto_1fr] sm:gap-x-3">
                            <dt>{{ __('marketplace.wallet.payment_methods.mandate.creditor_label') }}</dt>
                            <dd class="font-medium text-zinc-900">{{ $mandate['creditor_name'] }}</dd>
                            <dt>{{ __('marketplace.wallet.payment_methods.mandate.creditor_id_label') }}</dt>
                            <dd class="font-medium text-zinc-900">{{ $mandate['creditor_id'] }}</dd>
                        </dl>

                        <p class="mt-3 leading-relaxed text-zinc-700">{{ $mandate['text'] }}</p>
                        <p class="mt-2 text-xs text-zinc-500">{{ __('marketplace.wallet.payment_methods.mandate.prenotification_hint') }}</p>
                    </div>

                    <label class="mt-4 flex items-start gap-3 text-sm">
                        <input type="checkbox" data-pm-mandate required class="size-5 mt-0.5 shrink-0 rounded border-zinc-300 accent-current text-brand">
                        <span class="text-zinc-700">{{ __('marketplace.wallet.payment_methods.mandate.accept') }}</span>
                    </label>

                    <div class="mt-4 flex gap-2">
                        <button type="button" class="btn-ghost flex-1" data-pm-back="3">{{ __('portal.postpaid.payment_methods.back') }}</button>
                        <button type="button" class="btn-primary flex-1" data-pm-next="3">{{ __('portal.postpaid.payment_methods.save') }}</button>
                    </div>
                </div>

                {{-- Schritt 4: Bestaetigung. --}}
                <div data-pm-step="4" hidden>
                    <div class="flex items-start gap-3">
                        <span class="size-10 rounded-full bg-brand-50 text-brand flex items-center justify-center shrink-0">
                            <x-app.icon name="check" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-zinc-900">{{ __('portal.postpaid.payment_methods.done_heading') }}</h2>
                            <p class="text-sm text-zinc-500" data-pm-done-text></p>
                        </div>
                    </div>

                    <button type="button" class="btn-primary w-full mt-4" data-pm-close>{{ __('portal.postpaid.payment_methods.close') }}</button>
                </div>

                <p class="text-sm text-red-600 hidden" role="alert" data-pm-error></p>

                <p class="text-xs text-zinc-400 flex items-center gap-2">
                    <x-app.icon name="lock" class="size-4 shrink-0" />
                    {{ __('portal.topup.payment_hint') }}
                </p>

            </x-app.card>
        </div>

    </div>
</div>
