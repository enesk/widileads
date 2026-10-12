{{--
    Die Aktivierungskarte im Dashboard (LP-ONBOARD-012).

    <x-app.onboarding-card :items="$items" :done="$done" :total="$total" :hideable="true" />
    <x-app.onboarding-card activated />

    Zwei Zustaende in einer Datei, weil sie dieselbe Karte sind: die
    Checkliste bis zum ersten Anruf und der Abschluss, der genau einen Besuch
    lang steht. Welcher gilt, entscheidet App\Livewire\Portal\OnboardingCard --
    hier wird nur angezeigt.

    Markenlinie links, Illustration rechts ab sm. Der Ring statt eines Balkens:
    Ein Balken in einer Karte sieht aus wie Ladefortschritt; der Ring traegt die
    Zahl in der Mitte und sagt "3 von 5", nicht "60 %".

    Genau ein Primaerknopf: der naechste offene Schritt. Alle uebrigen Zeilen
    haben keinen -- die Komponente liefert `action` nur beim aktuellen Schritt.
--}}
@props([
    'items' => [],
    'done' => 0,
    'total' => 0,
    'hideable' => false,
    'activated' => false,
])

<section class="card border-l-4 border-l-brand p-5 md:p-6">

    @if ($activated)

        {{-- Abschluss: einmal gezeigt, danach nie wieder. Kein Knopf -- der
             Kaeufer ist angekommen und soll weiterarbeiten, nicht klicken. --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.onboarding.activation.activated.heading') }}</h2>
                <p class="text-zinc-500 mt-1">{{ __('portal.onboarding.activation.activated.text') }}</p>
            </div>
            {{-- max-w statt w: Die Regel .illustration {width:100%} aus
                 illustrations.css ist ungeschichtet und schlaegt jedes w-*;
                 begrenzen laesst sie sich nur ueber die Hoechstbreite. --}}
            <x-ui.illustration name="success" class="hidden sm:block max-w-28 shrink-0" :decorative="true" />
        </div>

    @else

        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">

                {{-- Der Ring: 48x48, ein Kreis als Spur, einer als Fortschritt.
                     Der Umfang steht in der CSS-Variablen, der Rest rechnet die
                     Datei progress-ring.css -- so gibt es keine zweite
                     Rechenweise fuer dieselbe Zahl. --}}
                <div @class(['progress-ring shrink-0', 'progress-ring--empty' => $done === 0])
                     style="--progress: {{ $total > 0 ? round($done / $total, 4) : 0 }}"
                     role="img"
                     aria-label="{{ __('portal.onboarding.activation.progress', ['done' => $done, 'total' => $total]) }}">
                    <svg viewBox="0 0 48 48" aria-hidden="true" focusable="false">
                        <circle class="progress-ring__track" cx="24" cy="24" r="20" />
                        <circle class="progress-ring__value" cx="24" cy="24" r="20" />
                    </svg>
                    <span class="progress-ring__label tabular-nums" aria-hidden="true">{{ $done }}</span>
                </div>

                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.onboarding.activation.heading') }}</h2>
                    <p class="text-sm text-zinc-500">{{ __('portal.onboarding.activation.progress', ['done' => $done, 'total' => $total]) }}</p>
                </div>
            </div>

            <x-ui.illustration name="checklist" class="hidden sm:block max-w-24 shrink-0" :decorative="true" />
        </div>

        <ul class="mt-4 space-y-1">
            @foreach ($items as $item)
                <li @class([
                        'flex flex-col sm:flex-row sm:items-center gap-3 py-2',
                        'border-t border-zinc-200' => ! $loop->first,
                    ])
                    wire:key="onboarding-step-{{ $item['key'] }}">

                    <span class="flex items-center gap-3 min-w-0 flex-1">
                        @if ($item['done'])
                            <span class="text-emerald-600 shrink-0"><x-app.icon name="check" class="size-5 shrink-0" /></span>
                        @else
                            <span @class([
                                'size-5 rounded-full border shrink-0',
                                'border-brand' => $item['current'],
                                'border-zinc-300' => ! $item['current'],
                            ])></span>
                        @endif

                        <span @class([
                            'min-w-0',
                            'line-through text-zinc-500' => $item['done'],
                            'font-semibold text-zinc-900' => $item['current'],
                            'text-zinc-700' => ! $item['done'] && ! $item['current'],
                        ])>{{ $item['label'] }}</span>
                    </span>

                    @if ($item['action'] !== null)
                        <a href="{{ $item['action']['url'] }}" class="btn-primary sm:w-auto shrink-0">
                            {{ $item['action']['label'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($hideable)
            <div class="mt-3 pt-3 border-t border-zinc-200">
                <button type="button" class="btn-ghost px-3 min-h-10 text-sm" wire:click="hide">
                    {{ __('portal.onboarding.activation.hide') }}
                </button>
            </div>
        @endif

    @endif

</section>
