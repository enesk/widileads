{{--
    Der Abschluss-Screen des Onboardings (LP-ONBOARD-011).

    Oben das Markenband mit der Ueberschrift und dem Motiv "success" klein
    daneben, darunter ab 768 Pixeln zwei Spalten: links der Beispiel-Lead zum
    Anfassen, rechts der 3-Schritte-Player. Auf dem Telefon liegt die
    Beispielkarte oben und der Player darunter.

    **Die drei Marker an der Karte** erklaeren je einen Satz: die Merkmale, das
    Schloss an der Rufnummer, den Preis. Sie sind das inline-Geschwister der
    gefuehrten Tour (LP-ONBOARD-012) -- dort laeuft ein Spotlight ueber den
    Marktplatz, hier steht der Hinweis an der Karte. Bewusst mit Alpine im
    Markup und nicht ueber das noch nicht gebaute Modul tour.js: Was dort
    entsteht, fuehrt eine Folge von Schritten ueber eine ganze Seite -- das hier
    ist ein Hinweis an einer Stelle.

    **Der Satz steht unter der Karte und nicht als Sprechblase am Marker.** Eine
    Blase neben einem Marker am rechten Rand laeuft auf 360 Pixeln aus dem
    Bildschirm; der Platz unter der Karte ist immer gleich breit. Es ist genau
    ein Bereich, auf den alle drei Marker ueber aria-controls zeigen -- Escape
    schliesst ihn.

    Erwartete Daten:
      $demoLead        die Felder von x-app.lead-card: name, funnel_name,
                       funnel_icon, postal_code, chips, phone (verdeckt),
                       phone_hint, price
      $demoPriceCents  Preis des Beispiels in Cent -- geht an den Player
      $balanceCents    Guthabenstand des Workspaces in Cent
      $marketplaceUrl  Marktplatz mit tour=marketplace
      $topUpUrl        Guthaben aufladen
      $showTopUp       Verweis auf die Aufladung zeigen?
      $deadlineDays, $unreachableAttempts   Werte der Hinweisbox
--}}
@php
    /* Die drei Marker. Die Reihenfolge ist die der Karte von oben nach
       unten -- so liest sie auch die Vorlesesoftware. */
    $markers = ['chips', 'phone', 'price'];
@endphp

<div>
    {{-- Das Markenband. Volle Markenflaechen sind die ausgewiesene
         widileads-Abweichung vom Skill sun-portal-design. --}}
    <div class="bg-brand text-white">
        <div class="container-portal py-8 md:py-10">
            <div class="mx-auto flex max-w-4xl items-center gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                        {{ __('portal.onboarding.done.heading') }}
                    </h1>
                    <p class="mt-2 max-w-prose text-sm text-white/85 md:text-base">
                        {{ __('portal.onboarding.done.text') }}
                    </p>
                </div>

                {{-- Klein im Band und nur Dekoration: Die Ueberschrift sagt
                     dasselbe. Die Breite kommt vom Rahmen, nicht von einer
                     size-Utility -- .illustration setzt width 100%. --}}
                <div class="hidden w-24 shrink-0 sm:block">
                    <x-ui.illustration name="success" :decorative="true" />
                </div>
            </div>
        </div>
    </div>

    <div class="container-portal py-8 md:py-10">
        <div class="mx-auto max-w-4xl">
            <div class="grid gap-6 md:grid-cols-2 md:items-start md:gap-8">

                {{-- Links: der Beispiel-Lead mit seinen drei Markern. --}}
                <div x-data="{ hint: null }" x-on:keydown.escape.window="hint = null">
                    {{-- Die Pille "Beispiel" bringt die Karte selbst mit; diese
                         Zeile praezisiert sie und steht deshalb darueber. --}}
                    <p class="mb-2 text-xs text-zinc-500">{{ __('portal.onboarding.done.demo.caption') }}</p>

                    {{-- Dieselbe Karte wie im Marktplatz, Variante "demo"
                         (x-app.lead-card, Ticket #20): ohne Kaufknopf, ohne
                         Klickflaeche, mit verdeckter Rufnummer. Die drei Marker
                         kommen als Slots herein und stehen damit an dem
                         Element, das sie erklaeren. Eine zweite Karte darum
                         braucht es nicht -- .card und p-4 bringt sie mit. --}}
                    <x-app.lead-card variant="demo" :lead="$demoLead">
                        <x-slot:marker-chips><x-ui.tour-marker id="chips" /></x-slot:marker-chips>
                        <x-slot:marker-phone><x-ui.tour-marker id="phone" /></x-slot:marker-phone>
                        <x-slot:marker-price><x-ui.tour-marker id="price" /></x-slot:marker-price>
                    </x-app.lead-card>

                    {{-- Der eine Hinweisbereich. Alle drei Saetze stehen im
                         HTML und werden nur ein- und ausgeblendet; ohne Skript
                         ist keiner offen und die Karte bleibt vollstaendig
                         lesbar. --}}
                    <div id="demo-lead-hint" role="status" class="mt-3">
                        @foreach ($markers as $marker)
                            <div
                                x-show="hint === @js($marker)"
                                style="display: none"
                                class="flex items-start gap-2 rounded-xl border border-zinc-200 bg-white p-3 text-sm text-zinc-700"
                            >
                                <x-app.icon name="info" class="text-brand mt-0.5 size-4 shrink-0" />
                                <span>{{ __('portal.onboarding.done.markers.'.$marker.'.hint') }}</span>
                                <button
                                    type="button"
                                    class="ml-auto shrink-0 rounded-lg p-0.5 text-zinc-400 hover:text-zinc-900"
                                    x-on:click="hint = null"
                                    aria-label="{{ __('portal.onboarding.done.markers.close') }}"
                                >
                                    <x-app.icon name="close" class="size-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Rechts: der Player. Dieselbe Komponente steht im Dialog von
                     Wizard-Schritt 3 und auf der Rufnummernseite. --}}
                <x-app.card class="p-5">
                    @livewire('portal.onboarding.billing-explainer', [
                        'priceCents' => $demoPriceCents,
                        'balanceCents' => $balanceCents,
                    ])
                </x-app.card>
            </div>

            {{-- Die Frist. Beide Zahlen aus config('lead_calls.*'). --}}
            <div class="bg-brand-50 mt-6 flex items-start gap-3 rounded-xl p-4">
                <x-app.icon name="clock" class="text-brand mt-0.5 size-5 shrink-0" />
                <p class="text-sm text-zinc-700">
                    {{ __('portal.onboarding.done.deadline', [
                        'days' => $deadlineDays,
                        'attempts' => $unreachableAttempts,
                    ]) }}
                </p>
            </div>

            {{-- Der Weg hinaus. Der Primaerknopf bekommt den Fokus: Die Seite
                 verlangt nichts, sie fuehrt weiter. --}}
            <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                <a href="{{ $marketplaceUrl }}" class="btn-primary w-full sm:w-auto" autofocus>
                    {{ __('portal.onboarding.done.to_marketplace') }}
                </a>

                @if ($showTopUp)
                    <a href="{{ $topUpUrl }}" class="btn-ghost w-full sm:w-auto">
                        {{ __('portal.onboarding.done.top_up_first') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
