{{--
    Styleguide des Portals.

    Erste Fassung, angelegt fuer die Abnahme des Illustrations-Sets
    (LP-ONBOARD-006). Sie zeigt nur die Bilder -- Farben, Knoepfe, Felder und
    Karten kommen mit LP-PORTAL-002 dazu. Der Aufbau (eine Sektion je
    Bausteingruppe) ist darauf vorbereitet.

    Jedes Motiv steht zweimal: auf Weiss und auf brand-50. Die Illustrationen
    arbeiten mit einer hellen Markenflaeche als Hintergrundform; auf getoentem
    Grund muss sie sich noch absetzen, sonst zerfaellt das Bild.
--}}
<x-layouts.portal-focus>

    <div class="container-portal section">

        <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">Styleguide</h1>
        <p class="mt-3 text-base leading-relaxed text-zinc-500 max-w-prose">
            Die Bausteine des Portals an einer Stelle. Was hier gleich aussieht, sieht im Portal gleich aus.
        </p>

        <h2 class="mt-12 text-2xl font-semibold text-zinc-900">Illustrationen</h2>
        <p class="mt-2 text-base leading-relaxed text-zinc-500 max-w-prose">
            Neun Motive, alle in einer Zeichenflaeche von 320 &times; 240 und ausschliesslich in
            Markenfarbe, Grau und Weiss. Faerbt ein Portal die Marke um, faerben sie mit.
            Aufruf: <code class="text-sm text-zinc-700">&lt;x-ui.illustration name="phone" /&gt;</code>
        </p>

        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['phone', 'sms', 'success', 'industries', 'region', 'payment', 'lead-preview', 'checklist', 'call'] as $motif)
                <div class="card p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-lg font-semibold text-zinc-900">{{ $motif }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div class="rounded-xl border border-zinc-200 bg-white p-3">
                            <x-ui.illustration :name="$motif" :decorative="true" />
                            <p class="mt-2 text-sm text-zinc-500 text-center">weiss</p>
                        </div>
                        <div class="rounded-xl border border-brand-100 bg-brand-50 p-3">
                            <x-ui.illustration :name="$motif" :decorative="true" />
                            <p class="mt-2 text-sm text-zinc-500 text-center">brand-50</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <h3 class="mt-12 text-lg font-semibold text-zinc-900">call: die drei Zustaende</h3>
        <p class="mt-2 text-base leading-relaxed text-zinc-500 max-w-prose">
            Fuer die Abrechnungs-Erklaerung. Gewechselt wird nur <code class="text-sm text-zinc-700">data-state</code>;
            angenommen traegt die Markenfarbe, verpasst ist grau. Die Bewegung dauert 400 ms und
            entfaellt, wenn im Betriebssystem weniger Bewegung eingestellt ist.
        </p>

        <div class="mt-6 grid gap-6 sm:grid-cols-3">
            @foreach (['ringing' => 'Es klingelt', 'answered' => 'Angenommen &ndash; kostet', 'missed' => 'Verpasst &ndash; kostenlos'] as $state => $caption)
                <div class="card p-4">
                    <x-ui.illustration name="call" :state="$state" :decorative="true" />
                    <p class="mt-3 text-sm text-zinc-500 text-center">{!! $caption !!}</p>
                </div>
            @endforeach
        </div>

        <h2 class="mt-12 text-2xl font-semibold text-zinc-900">Lead-Karte</h2>
        <p class="mt-2 text-base leading-relaxed text-zinc-500 max-w-prose">
            <code class="text-sm text-zinc-700">&lt;x-app.lead-card&gt;</code> in zwei Varianten.
            <code class="text-sm text-zinc-700">marketplace</code> steht im Marktplatz: Der Name
            traegt die Flaeche der ganzen Karte, der Kaufknopf kommt als Slot
            <code class="text-sm text-zinc-700">action</code> herein, der Klick als
            <code class="text-sm text-zinc-700">titleAttributes</code> &mdash; die Komponente selbst
            kennt kein Livewire. <code class="text-sm text-zinc-700">demo</code> ist dieselbe Karte
            ohne Kaufknopf und ohne Klickflaeche, mit grauer Pille &bdquo;Beispiel&ldquo; und
            verdeckter Rufnummer; ob verdeckt wird, entscheidet der
            <code class="text-sm text-zinc-700">LeadPresenter</code>, nicht die Karte.
            In <code class="text-sm text-zinc-700">marketplace</code> rutschen Zeit, Preis und
            Knopf unter 900&nbsp;Pixeln als Zeile unter die Karte;
            <code class="text-sm text-zinc-700">demo</code> steht immer so &mdash; sie steht nie
            in voller Breite, und die Media-Query misst den Viewport, nicht die Spalte.
            Fuer die Hinweis-Marker hat <code class="text-sm text-zinc-700">demo</code> die drei
            Slots <code class="text-sm text-zinc-700">marker-chips</code>,
            <code class="text-sm text-zinc-700">marker-phone</code> und
            <code class="text-sm text-zinc-700">marker-price</code>; sie brauchen ein
            <code class="text-sm text-zinc-700">x-data="{ hint: null }"</code> am Rahmen.
        </p>

        @php
            /* Nur fuer diese Seite: eine Karte braucht fertige Werte, keinen
               Datensatz -- genau das ist der Zweck der Variante "demo". */
            $exampleLead = [
                'id' => 0,
                'name' => 'Marie Hoffmann',
                'funnel_name' => 'Badsanierung',
                'funnel_icon' => 'wrench',
                'portal' => 'sanitaerfinder.com',
                'company' => null,
                'postal_code' => '76131',
                'chips' => ['Komplettbad', 'Eigentum', 'Innerhalb von 4 Wochen'],
                'created_at' => 'vor 2 Stunden',
                'created_at_exact' => '12.10.2026 09:14',
                'is_new' => true,
                'price' => '14,90 €',
                'purchasable' => true,
            ];
        @endphp

        <div class="mt-8 flex flex-col gap-6">
            <div>
                <p class="mb-2 text-sm font-medium text-zinc-500">variant="marketplace"</p>
                <x-app.lead-card :lead="$exampleLead">
                    <x-slot:action>
                        <button type="button" class="btn-primary whitespace-nowrap">
                            <x-app.icon name="cart" class="size-5" />
                            Kaufen
                        </button>
                    </x-slot:action>
                </x-app.lead-card>
            </div>

            {{-- Der Rahmen traegt das x-data, das die Marker setzen -- ohne
                 das greifen sie ins Leere. Einen Hinweisbereich zeigt diese
                 Seite nicht: Wohin der Satz geht, entscheidet der Screen. --}}
            <div x-data="{ hint: null }">
                <p class="mb-2 text-sm font-medium text-zinc-500">variant="demo"</p>
                <x-app.lead-card
                    variant="demo"
                    :lead="array_merge($exampleLead, [
                        'phone' => '+49 172 ••• ••67',
                        'phone_hint' => 'Die Nummer wird frei, sobald du den Lead gekauft hast.',
                    ])"
                >
                    <x-slot:marker-chips><x-ui.tour-marker id="chips" /></x-slot:marker-chips>
                    <x-slot:marker-phone><x-ui.tour-marker id="phone" /></x-slot:marker-phone>
                    <x-slot:marker-price><x-ui.tour-marker id="price" /></x-slot:marker-price>
                </x-app.lead-card>
            </div>
        </div>

        <h2 class="mt-12 text-2xl font-semibold text-zinc-900">Aktionsleiste</h2>
        <p class="mt-2 text-base leading-relaxed text-zinc-500 max-w-prose">
            <code class="text-sm text-zinc-700">&lt;x-app.bottom-bar&gt;</code> mit den Slots
            <code class="text-sm text-zinc-700">primary</code> (Pflicht, rechts),
            <code class="text-sm text-zinc-700">secondary</code> (links, ghost) und
            <code class="text-sm text-zinc-700">note</code> (ein Satz darueber). Auf dem Telefon
            heftet sie sich an den unteren Rand und der Primaerknopf nimmt zwei Drittel der
            Breite; ab 768&nbsp;Pixeln stehen dieselben Knoepfe rechtsbuendig im Fluss, Rand und
            Flaeche entfallen. Den Platz am Seitenende reserviert die Komponente selbst &mdash;
            der Screen setzt dazu nichts.
        </p>
        <p class="mt-2 text-base leading-relaxed text-zinc-500 max-w-prose">
            Diese Seite zeigt sie unten im Bild einmal echt (festgeheftet, mit Zusatzzeile).
            Die Varianten darunter stehen in einer Karte und laufen deshalb mit
            <code class="text-sm text-zinc-700">:inline="true"</code>, damit alle drei
            gleichzeitig sichtbar sind.
        </p>

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <div class="card p-5">
                <p class="text-sm font-medium text-zinc-500">Zwei Knoepfe mit Zusatzzeile</p>
                <div class="mt-4">
                    <x-app.bottom-bar :inline="true">
                        <x-slot:note>
                            <button type="button" class="underline hover:text-zinc-900">Spaeter einrichten</button>
                        </x-slot:note>
                        <x-slot:secondary>
                            <button type="button" class="btn-ghost">Zurueck</button>
                        </x-slot:secondary>
                        <x-slot:primary>
                            <button type="button" class="btn-primary">Weiter</button>
                        </x-slot:primary>
                    </x-app.bottom-bar>
                </div>
            </div>

            <div class="card p-5">
                <p class="text-sm font-medium text-zinc-500">Zwei Knoepfe</p>
                <div class="mt-4">
                    <x-app.bottom-bar :inline="true">
                        <x-slot:secondary>
                            <button type="button" class="btn-ghost">Zurueck</button>
                        </x-slot:secondary>
                        <x-slot:primary>
                            <button type="button" class="btn-primary">Weiter</button>
                        </x-slot:primary>
                    </x-app.bottom-bar>
                </div>
            </div>

            <div class="card p-5">
                <p class="text-sm font-medium text-zinc-500">Nur der Primaerknopf</p>
                <div class="mt-4">
                    <x-app.bottom-bar :inline="true">
                        <x-slot:primary>
                            <button type="button" class="btn-primary">Weiter zum Checkout</button>
                        </x-slot:primary>
                    </x-app.bottom-bar>
                </div>
            </div>
        </div>

        {{-- Einmal echt: festgeheftet auf dem Telefon, ab md rechtsbuendig im
             Fluss am Seitenende. Die Kopfzeile dieses Layouts scrollt mit --
             fixiert ist damit nur ein Element. --}}
        <x-app.bottom-bar :divider="true" class="md:mt-10">
            <x-slot:note>So sieht sie im Einsatz aus</x-slot:note>
            <x-slot:secondary>
                <a href="{{ route('home') }}" class="btn-ghost">Zurueck</a>
            </x-slot:secondary>
            <x-slot:primary>
                <button type="button" class="btn-primary">Weiter</button>
            </x-slot:primary>
        </x-app.bottom-bar>

    </div>

</x-layouts.portal-focus>
