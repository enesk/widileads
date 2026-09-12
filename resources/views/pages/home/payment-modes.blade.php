@php
    /**
     * Zwei Wege zu den Leads: Guthaben (Standard) und Pay as you go (Postpaid).
     *
     * Saemtliche Zahlen kommen aus config('wallet.postpaid.*') -- steht dort ein
     * anderer Aufschlag oder eine andere Huerde, aendert sich der Text mit.
     */
    $postpaid = config('wallet.postpaid', ['enabled' => false]);
@endphp

@if ($postpaid['enabled'] ?? false)
    @php
        $surcharge = rtrim(rtrim(number_format((float) $postpaid['surcharge_percent'], 1, ',', '.'), '0'), ',');
        $creditLimit = \App\Support\Money::format((int) $postpaid['default_credit_limit_cents']);
        $minPurchases = (int) $postpaid['min_captured_purchases'];
    @endphp
    <section id="bezahlung" class="section">
      <div class="container-portal">
        <h2 class="text-2xl font-semibold text-zinc-900 text-center">Zwei Wege zu deinen Leads</h2>
        <p class="text-zinc-600 text-center mt-2 max-w-2xl mx-auto">Du zahlst immer nur für das, was du bekommst. Ob vorher oder nachher, entscheidest du.</p>

        <div class="grid gap-4 md:grid-cols-2 mt-8 items-stretch">
          <div class="card p-6 md:p-8 flex flex-col">
            <span class="size-11 rounded-xl bg-brand-50 text-brand flex items-center justify-center"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
            <h3 class="text-xl font-semibold text-zinc-900 mt-4">Guthaben</h3>
            <p class="text-zinc-600 mt-2">Du lädst auf und kaufst davon. Für alle, ohne Antrag, ab dem ersten Tag.</p>
            <ul class="space-y-3 text-zinc-700 mt-5">
              <li class="flex items-start gap-2"><svg class="size-5 text-brand shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Sofort starten, ohne Freischaltung</li>
              <li class="flex items-start gap-2"><svg class="size-5 text-brand shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Volle Kontrolle: mehr als dein Guthaben kannst du nicht ausgeben</li>
              <li class="flex items-start gap-2"><svg class="size-5 text-brand shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Kein Aufschlag, du zahlst den Leadpreis</li>
            </ul>
            <p class="text-sm text-zinc-500 mt-auto pt-6">Zu jeder Aufladung bekommst du eine Rechnung. Dein Guthaben verfällt nicht.</p>
          </div>

          <div class="rounded-2xl bg-brand text-white p-6 md:p-8 flex flex-col">
            <div class="flex items-start justify-between gap-4">
              <span class="size-11 rounded-xl bg-white/15 text-white flex items-center justify-center"><svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
              <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide">Für Stammkunden</span>
            </div>
            <h3 class="text-xl font-semibold mt-4">Pay as you go</h3>
            <p class="text-white/85 mt-2">Du kaufst ohne Vorauszahlung und zahlst erst, wenn der Kunde wirklich erreicht wurde.</p>
            <ul class="space-y-3 text-white/90 mt-5">
              <li class="flex items-start gap-2"><svg class="size-5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Leads kaufen ohne Guthaben aufzuladen</li>
              <li class="flex items-start gap-2"><svg class="size-5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Abgerechnet wird nur, was du als erreichten Kunden abschließt</li>
              <li class="flex items-start gap-2"><svg class="size-5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Automatischer Einzug per Lastschrift oder Karte</li>
              <li class="flex items-start gap-2"><svg class="size-5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Freischaltung nach deinen ersten {{ $minPurchases }} abgerechneten Leads</li>
              <li class="flex items-start gap-2"><svg class="size-5 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Aufschlag {{ $surcharge }} % auf den Leadpreis, Rahmen bis {{ $creditLimit }}</li>
            </ul>
            <p class="text-sm text-white/70 mt-6">Pay as you go beantragst du später in deinem Konto. Zum Start reicht Guthaben.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Aktuelle Anfragen -->
@endif
