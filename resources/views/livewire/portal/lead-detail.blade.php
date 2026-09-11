{{--
    Ein gekaufter Lead im Portal (Portal Phase 1).

    Markup nach dem Entwurf `lead-detail.html`: links die Inhalte, rechts die
    Spalte mit dem, was die Entscheidung treibt. Der Rahmen kommt aus
    x-layouts.portal-app -- dort steht bereits das <main> samt Abstaenden.

    Diese Ansicht entscheidet nichts. Sie rechnet nicht und ruft keinen Dienst
    auf: Frist, Zaehler, Preis und Kontaktdaten liegen fertig vor, Letztere so,
    wie der LeadPresenter sie geliefert hat.
--}}
<div @if ($this->shouldPoll()) wire:poll.5s="refreshStatus" @endif>

    @if ($notice !== null)
        <div role="status" class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ $notice }}
        </div>
    @endif

    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline">
        <x-app.icon name="arrow-left" class="size-4 shrink-0" />
        {{ __('marketplace.purchased.portal.back') }}
    </a>

    <div class="mt-3 flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $name }}</h1>

                @if ($deadlineToday && ! $deadlinePassed)
                    <span class="pill bg-amber-50 text-amber-800">
                        <x-app.icon name="clock" class="size-4 shrink-0" />
                        {{ __('marketplace.purchased.portal.deadline.today') }}
                    </span>
                @endif
            </div>

            <p class="text-zinc-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="inline-flex items-center gap-1.5">
                    <x-app.icon name="pin" class="size-4 text-zinc-400 shrink-0" />
                    {{ $postalCode }}
                </span>
                <span>·</span>
                <span>{{ $funnelName }}</span>
                <span>·</span>
                <span>{{ $purchasedRelative }}</span>
            </p>
        </div>

        <div class="flex flex-col sm:flex-row gap-2 shrink-0">
            <a href="mailto:{{ $email }}" class="btn-secondary">
                <x-app.icon name="mail" class="size-4 shrink-0" />
                {{ __('marketplace.purchased.portal.email_action') }}
            </a>

            <button type="button" class="btn-primary" wire:click="startCall" @disabled(! $canCall) @if (! $canCall) title="{{ $blockedReason }}" @endif>
                <x-app.icon name="phone" />
                {{ __('marketplace.purchased.portal.deadline.call') }}
            </button>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem] items-start">

        <div class="space-y-6 min-w-0">

            <section class="card p-5 md:p-6">
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.purchased.portal.contact') }}</h2>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 border border-zinc-200 p-4">
                        <p class="text-sm text-zinc-500 flex items-center gap-2">
                            <x-app.icon name="lock" class="size-4 shrink-0" />
                            {{ __('leads.contact.phone') }}
                        </p>
                        <p @class([
                            'mt-1 text-lg font-semibold tabular-nums',
                            'text-zinc-400' => $phoneMasked,
                            'text-zinc-900' => ! $phoneMasked,
                        ])>{{ $phone }}</p>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ __('marketplace.purchased.portal.phone_hint_masked') }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 border border-zinc-200 p-4">
                        <p class="text-sm text-zinc-500 flex items-center gap-2">
                            <x-app.icon name="mail" class="size-4 text-zinc-400 shrink-0" />
                            {{ __('leads.contact.email') }}
                        </p>
                        <a href="mailto:{{ $email }}" class="mt-1 block text-lg font-semibold text-zinc-900 hover:underline truncate">{{ $email }}</a>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ __('marketplace.purchased.portal.email_hint') }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="card p-5 md:p-6">
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.purchased.portal.request') }}</h2>

                @if ($requestText === null)
                    <p class="mt-3 text-zinc-500">{{ __('marketplace.purchased.portal.no_request_text') }}</p>
                @else
                    <blockquote class="mt-3 text-zinc-700 leading-relaxed border-l-2 border-brand pl-4">{{ $requestText }}</blockquote>
                @endif

                <p class="mt-3 text-sm text-zinc-500">
                    {{ __('marketplace.purchased.portal.request_received', [
                        'date' => $lead->created_at?->format('d.m.Y, H:i') ?? '-',
                        'funnel' => $funnelName,
                    ]) }}
                </p>
            </section>

            <section class="card p-5 md:p-6">
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.purchased.portal.attributes') }}</h2>

                @if ($answers === [])
                    <p class="mt-3 text-zinc-500">{{ __('marketplace.purchased.portal.no_attributes') }}</p>
                @else
                    <dl class="mt-4 grid grid-cols-[auto_1fr] sm:grid-cols-[auto_1fr_auto_1fr] gap-x-6 gap-y-2 text-sm">
                        @foreach ($answers as $label => $value)
                            <dt class="text-zinc-500">{{ $label }}</dt>
                            <dd class="text-zinc-900 font-medium">{{ $value }}</dd>
                        @endforeach
                    </dl>
                @endif
            </section>

            {{-- Notizen: gespeichert wird mit Verzoegerung, nicht bei jedem
                 Tastendruck. Der Stand steht am Feld, damit niemand raten muss,
                 ob seine Eingabe angekommen ist. --}}
            <section class="card p-5 md:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.purchased.notes.heading') }}</h2>
                    <span class="text-xs text-zinc-400">
                        <span wire:loading wire:target="notes">{{ __('marketplace.purchased.notes.saving') }}</span>
                        <span wire:loading.remove wire:target="notes">{{ __('marketplace.purchased.notes.saved') }}</span>
                    </span>
                </div>

                <label for="notes" class="sr-only">{{ __('marketplace.purchased.notes.heading') }}</label>
                <textarea
                    id="notes"
                    rows="4"
                    class="input mt-4 resize-y"
                    placeholder="{{ __('marketplace.purchased.notes.placeholder') }}"
                    wire:model.live.debounce.800ms="notes"
                >{{ $notes }}</textarea>

                <p class="mt-2 text-xs text-zinc-500">{{ __('marketplace.purchased.notes.hint') }}</p>
            </section>

        </div>

        <aside class="space-y-6">

            {{-- Das Herzstueck: was mit Frist und Geld passiert, und was der
                 naechste Schritt daran aendert. --}}
            <section @class(['card p-5', 'border-amber-200' => $deadlineToday && ! $deadlinePassed])>
                <h2 class="text-sm font-medium text-zinc-500">{{ __('marketplace.purchased.portal.deadline.heading') }}</h2>

                <p class="mt-1 text-2xl font-semibold text-zinc-900 tabular-nums">
                    @if ($deadline === null)
                        {{ __('marketplace.purchased.portal.deadline.none') }}
                    @elseif ($deadlinePassed)
                        {{ __('marketplace.purchased.portal.deadline.ended') }}
                    @else
                        {{ __('marketplace.purchased.portal.deadline.ends', [
                            'date' => $deadlineToday ? __('marketplace.purchased.portal.deadline.today_word') : $deadline->format('d.m.Y'),
                            'time' => $deadline->format('H:i'),
                        ]) }}
                    @endif
                </p>

                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">{{ __('marketplace.purchased.portal.deadline.attempts') }}</span>
                        <span class="font-medium text-zinc-900">{{ __('marketplace.purchased.portal.deadline.attempts_value', ['done' => $failed, 'total' => $required]) }}</span>
                    </div>

                    <div class="h-2 rounded-full bg-zinc-100 overflow-hidden">
                        <div class="h-full bg-brand rounded-full" style="width: {{ $required > 0 ? min(100, (int) round($failed / $required * 100)) : 0 }}%"></div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">{{ __('marketplace.purchased.portal.deadline.next') }}</span>
                        @if ($canCall)
                            <span class="font-medium text-emerald-700">{{ __('marketplace.purchased.portal.deadline.next_now') }}</span>
                        @elseif ($nextAllowedAt !== null)
                            <span class="font-medium text-zinc-900 tabular-nums">{{ __('marketplace.purchased.portal.deadline.next_at', ['time' => $nextAllowedAt->format('H:i')]) }}</span>
                        @else
                            <span class="font-medium text-zinc-500">–</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500">{{ __('marketplace.purchased.portal.deadline.price') }}</span>
                        <span class="font-medium text-zinc-900 tabular-nums">{{ $price }}</span>
                    </div>
                </div>

                {{-- Die Stelle, an der Kaeufer sonst im Support nachfragen:
                     beide Wege in einem Satz. --}}
                <div class="mt-4 rounded-xl bg-brand-50 p-3 text-sm text-brand-700 flex gap-2">
                    <x-app.icon name="info" class="size-5 text-brand shrink-0" />
                    <span>
                        @if ($isOpen && $remaining > 0)
                            {{ trans_choice('marketplace.purchased.portal.deadline.consequence', $remaining, ['count' => $remaining, 'amount' => $price]) }}
                        @else
                            {{ $priceStatus }}
                        @endif
                    </span>
                </div>

                @if ($callingHint !== null)
                    <p class="mt-3 text-sm text-zinc-500">{{ $callingHint }}</p>
                @elseif ($blockedReason !== null)
                    <p class="mt-3 text-sm text-zinc-500">{{ $blockedReason }}</p>
                @endif

                <button type="button" class="btn-primary w-full mt-4" wire:click="startCall" @disabled(! $canCall)>
                    <x-app.icon name="phone" />
                    {{ __('marketplace.purchased.portal.deadline.call') }}
                </button>

                {{-- Fuehrt zu den beiden Regeln unter dem Versuchsverlauf --
                     eine eigene Hilfeseite gibt es noch nicht. --}}
                <a href="#anrufe" class="block text-center text-sm text-brand hover:underline mt-3">
                    {{ __('marketplace.purchased.portal.calls.how_billing_works') }}
                </a>
            </section>

            <section class="card p-5" id="anrufe">
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.purchased.portal.calls.heading') }}</h2>

                @if ($attempts === [])
                    <p class="mt-3 text-sm text-zinc-500">{{ __('marketplace.purchased.portal.calls.empty') }}</p>
                @else
                    <ul class="mt-2 divide-y divide-zinc-200">
                        @foreach ($attempts as $attempt)
                            <li class="flex items-start gap-3 py-3">
                                <span class="size-8 rounded-full bg-zinc-100 text-zinc-500 flex items-center justify-center shrink-0">
                                    <x-app.icon :name="$attempt['icon']" class="size-4 shrink-0" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-zinc-900">{{ $attempt['result'] }}</p>
                                    <p class="text-sm text-zinc-500">{{ $attempt['when'] }}</p>
                                    @if ($attempt['note'] !== null)
                                        <p class="text-xs text-zinc-400">{{ __('marketplace.purchased.portal.calls.not_counted', ['reason' => $attempt['note']]) }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="text-xs text-zinc-500 pt-3 border-t border-zinc-200">
                    {{ __('call.panel.rule_hint', [
                        'attempts' => $required,
                        'days' => (int) config('lead_calls.unreachable_min_days'),
                        'hours' => (int) config('lead_calls.retry_min_hours'),
                        'deadline' => $deadline?->format('d.m.Y') ?? '-',
                    ]) }}
                </p>
            </section>

            {{-- Arbeitsstand: ausdruecklich als Merkhilfe markiert, damit
                 niemand glaubt, "Kein Bedarf" hebe die Abrechnung auf. --}}
            <section class="card p-5">
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('marketplace.purchased.status.label') }}</h2>

                <div class="relative mt-3">
                    <label for="status" class="sr-only">{{ __('marketplace.purchased.status.label') }}</label>
                    <select id="status" class="input appearance-none pr-10" wire:model.live="status">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($value === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                        <x-app.icon name="chevron-down" class="size-5 text-zinc-400 shrink-0" />
                    </span>
                </div>

                <p class="mt-2 text-xs text-zinc-500">{{ __('marketplace.purchased.status.hint') }}</p>
            </section>

            <section class="card p-5">
                <h2 class="text-sm font-medium text-zinc-500">{{ __('marketplace.purchased.portal.facts.heading') }}</h2>

                <dl class="mt-2 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">{{ __('marketplace.purchased.portal.facts.purchased_at') }}</dt>
                        <dd class="text-zinc-900">{{ $purchase->purchased_at?->format('d.m.Y, H:i') ?? '-' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">{{ __('marketplace.purchased.portal.facts.lead_number') }}</dt>
                        <dd class="text-zinc-900 tabular-nums">{{ $lead->getKey() }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">{{ __('marketplace.purchased.portal.facts.source') }}</dt>
                        <dd class="text-zinc-900">{{ $funnelName }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-500">{{ __('marketplace.purchased.portal.facts.workspace') }}</dt>
                        <dd class="text-zinc-900">{{ $purchase->buyer?->name }}</dd>
                    </div>
                </dl>

                <a href="{{ $complaintUrl }}" class="block text-sm text-brand hover:underline mt-4">
                    {{ __('marketplace.purchased.portal.facts.complaint') }}
                </a>
            </section>

        </aside>
    </div>
</div>
