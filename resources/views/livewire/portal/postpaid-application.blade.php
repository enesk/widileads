{{--
    Der Abschnitt "Pay as you go" auf der Guthabenseite (LP-POSTPAID-010).

    Drei moegliche Zustaende, immer nur einer davon: die Eignungs-Checkliste mit
    dem Antragsknopf, "Antrag eingereicht" oder "Abgelehnt". Der Knopf ist nur
    aktiv, wenn alle Punkte erfuellt sind -- geprueft wird trotzdem noch einmal
    im PostpaidService.

    Erwartete Daten:
      $visible, $points, $rules (text, met), $pending, $rejected, $blockedDays,
      $hasPaymentMethod, $canApply, $paymentMethodsUrl
--}}
{{-- Livewire braucht genau ein Wurzelelement; bei Postpaid bleibt es leer. --}}
<div>
    @if ($visible)
        <x-app.card class="p-5 md:p-6 space-y-5">

            <div class="flex items-start gap-4">
                <span class="size-12 rounded-xl bg-brand-50 text-brand flex items-center justify-center shrink-0">
                    <x-app.icon name="card" class="size-6" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.postpaid.apply.heading') }}</h2>
                    <p class="text-sm text-zinc-500">{{ __('portal.postpaid.apply.description') }}</p>
                </div>
            </div>

            <ul class="space-y-2 text-sm text-zinc-700">
                @foreach ($points as $point)
                    <li class="flex gap-2">
                        <span class="text-brand mt-0.5 shrink-0"><x-app.icon name="check" class="size-4" /></span>
                        {{ $point }}
                    </li>
                @endforeach
            </ul>

            @if ($pending !== null)

                <div class="rounded-xl border border-brand-100 bg-brand-50 p-4">
                    <p class="font-semibold text-brand-700">{{ __('portal.postpaid.apply.pending_heading') }}</p>
                    <p class="text-sm text-brand-700 mt-1">
                        {{ __('portal.postpaid.apply.pending_text', ['date' => $pending->requested_at?->format('d.m.Y') ?? '']) }}
                    </p>
                </div>

            @elseif ($rejected !== null && $blockedDays > 0)

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="font-semibold text-amber-900">{{ __('portal.postpaid.apply.rejected_heading') }}</p>
                    <p class="text-sm text-amber-900 mt-1">
                        {{ __('portal.postpaid.apply.rejected_text') }}
                        {{ __('portal.postpaid.apply.rejected_retry', ['days' => $blockedDays]) }}
                    </p>
                </div>

            @else

                <div class="border-t border-zinc-200 pt-4">
                    <h3 class="text-sm font-medium text-zinc-700">{{ __('portal.postpaid.apply.checklist_heading') }}</h3>

                    <ul class="mt-3 space-y-2.5">
                        @foreach ($rules as $rule)
                            <li class="flex items-start gap-2.5 text-sm">
                                <span @class([
                                    'size-5 rounded-full flex items-center justify-center shrink-0 mt-0.5',
                                    'bg-brand-50 text-brand' => $rule['met'],
                                    'bg-zinc-100 text-zinc-400' => ! $rule['met'],
                                ])>
                                    <x-app.icon :name="$rule['met'] ? 'check' : 'close'" class="size-3.5" />
                                </span>
                                <span class="min-w-0 flex-1 {{ $rule['met'] ? 'text-zinc-700' : 'text-zinc-500' }}">
                                    {{ $rule['text'] }}
                                </span>
                                <x-app.badge :variant="$rule['met'] ? 'brand' : 'neutral'" class="shrink-0 text-xs">
                                    {{ $rule['met'] ? __('portal.postpaid.apply.rule_met') : __('portal.postpaid.apply.rule_open') }}
                                </x-app.badge>
                            </li>
                        @endforeach
                    </ul>

                    @unless ($hasPaymentMethod)
                        <p class="mt-3 text-sm text-zinc-500">{{ __('portal.postpaid.apply.payment_method_hint') }}</p>
                    @endunless

                    <div class="mt-4 flex flex-col sm:flex-row gap-2">
                        <button
                            type="button"
                            class="btn-primary"
                            wire:click="apply"
                            wire:loading.attr="disabled"
                            @disabled(! $canApply)
                        >
                            {{ __('portal.postpaid.apply.submit') }}
                        </button>

                        @unless ($hasPaymentMethod)
                            <a href="{{ $paymentMethodsUrl }}" class="btn-secondary">
                                <x-app.icon name="card" />
                                {{ __('portal.postpaid.apply.payment_method_cta') }}
                            </a>
                        @endunless
                    </div>

                    @unless ($canApply)
                        <p class="mt-2 text-xs text-zinc-500">
                            {{ $blockedDays > 0
                                ? __('portal.postpaid.apply.rejected_retry', ['days' => $blockedDays])
                                : __('portal.postpaid.apply.not_eligible_hint') }}
                        </p>
                    @endunless
                </div>

            @endif

        </x-app.card>
    @endif
</div>
