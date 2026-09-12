@php
    /**
     * Die Postpaid-Fragen stehen nur, wenn Pay as you go ueberhaupt laeuft, und
     * nennen ausschliesslich Werte aus config('wallet.postpaid.*') -- keine
     * Bedingung wird im Text gerundet oder weggelassen.
     */
    $postpaid = config('wallet.postpaid', ['enabled' => false]);
@endphp

<section id="faq" class="section bg-white border-y border-zinc-200">
  <div class="container-portal">
    <h2 class="text-2xl font-semibold text-zinc-900 mb-6 text-center">Fragen, die uns oft gestellt werden</h2>
    <div class="card divide-y divide-zinc-200 max-w-3xl mx-auto">
      <details open>
        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer font-medium text-zinc-900 list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-2xl">Wann gilt ein Anruf als angenommen?<svg class="chev size-5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p class="px-5 pb-5 text-zinc-600 leading-relaxed">Ab 30 Sekunden Gesprächsdauer. Kürzere Anrufe und Mailbox zählen nicht.</p>
      </details>
      <details>
        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer font-medium text-zinc-900 list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Was, wenn niemand abnimmt?<svg class="chev size-5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p class="px-5 pb-5 text-zinc-600 leading-relaxed">Du versuchst es dreimal an mindestens zwei Tagen mit je zwei Stunden Abstand. Klappt es dann nicht, wird die Anfrage nicht berechnet. Die Frist dafür sind 7 Tage.</p>
      </details>
      <details>
        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer font-medium text-zinc-900 list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Woher kommen die Anfragen?<svg class="chev size-5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p class="px-5 pb-5 text-zinc-600 leading-relaxed">Von unseren eigenen Branchenportalen wie fliesenleger.io oder tierarztportal.com. Menschen stellen dort eine konkrete Anfrage mit ihren Angaben.</p>
      </details>
      <details>
        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer font-medium text-zinc-900 list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Wie bezahle ich?<svg class="chev size-5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p class="px-5 pb-5 text-zinc-600 leading-relaxed">Du lädst Guthaben auf – per Karte oder Rechnung – und kaufst damit Anfragen. Zu jedem Guthaben-Kauf bekommst du eine Rechnung.</p>
      </details>
      <details>
        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer font-medium text-zinc-900 list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand{{ ($postpaid['enabled'] ?? false) ? '' : ' rounded-2xl' }}">Kann ich Anfragen vorher sehen?<svg class="chev size-5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p class="px-5 pb-5 text-zinc-600 leading-relaxed">Ja, alle Merkmale außer den Kontaktdaten. Auch ohne Konto.</p>
      </details>
      @if ($postpaid['enabled'] ?? false)
        @php
            $surcharge = rtrim(rtrim(number_format((float) $postpaid['surcharge_percent'], 1, ',', '.'), '0'), ',');
            $weekday = [
                'monday' => 'montags', 'tuesday' => 'dienstags', 'wednesday' => 'mittwochs',
                'thursday' => 'donnerstags', 'friday' => 'freitags', 'saturday' => 'samstags',
                'sunday' => 'sonntags',
            ][strtolower((string) $postpaid['settlement_weekday'])] ?? 'woechentlich';
        @endphp
      <details>
        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer font-medium text-zinc-900 list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Wann kann ich Pay as you go nutzen?<svg class="chev size-5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p class="px-5 pb-5 text-zinc-600 leading-relaxed">Nicht sofort: Du beantragst es in deinem Konto, sobald du mindestens {{ (int) $postpaid['min_captured_purchases'] }} abgerechnete Leads gekauft hast, dein Konto mindestens {{ (int) $postpaid['min_account_age_days'] }} Tage alt ist und es in den letzten {{ (int) $postpaid['clean_history_days'] }} Tagen keine Zahlungsstörung gab. Wir prüfen den Antrag und schalten dich frei. Bis dahin kaufst du mit Guthaben.</p>
      </details>
      <details>
        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer font-medium text-zinc-900 list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Was kostet Pay as you go?<svg class="chev size-5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p class="px-5 pb-5 text-zinc-600 leading-relaxed">Auf jeden Lead kommt ein Aufschlag von {{ $surcharge }} % des Leadpreises. Der Satz wird beim Kauf festgehalten, spätere Änderungen verteuern deine laufenden Käufe nicht. Dein Kreditrahmen beträgt zum Start {{ \App\Support\Money::format((int) $postpaid['default_credit_limit_cents']) }}; mehr offene Beträge kannst du nicht ansammeln.</p>
      </details>
      <details>
        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer font-medium text-zinc-900 list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand">Wann wird abgebucht?<svg class="chev size-5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p class="px-5 pb-5 text-zinc-600 leading-relaxed">Regulär {{ $weekday }} ab {{ $postpaid['settlement_time'] }} Uhr wird alles eingezogen, was seit dem letzten Einzug abgerechnet wurde. Erreicht dein offener Betrag vorher {{ \App\Support\Money::format((int) $postpaid['settlement_threshold_cents']) }}, ziehen wir sofort ein. Abgebucht wird vom hinterlegten Zahlungsmittel, per SEPA-Lastschrift oder Karte.</p>
      </details>
      <details>
        <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer font-medium text-zinc-900 list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-2xl">Was passiert, wenn eine Abbuchung fehlschlägt?<svg class="chev size-5 text-zinc-400 shrink-0 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p class="px-5 pb-5 text-zinc-600 leading-relaxed">Wir versuchen es nach {{ (int) $postpaid['retry_after_days'] }} Tagen erneut. Bis der offene Betrag ausgeglichen ist, kannst du keine weiteren Leads kaufen, und du wirst auf Guthaben zurückgestuft. Gibt deine Bank die Lastschrift zurück, kommen {{ \App\Support\Money::format((int) $postpaid['return_fee_cents']) }} Rücklastschriftgebühr dazu, bei einer Mahnung {{ \App\Support\Money::format((int) $postpaid['dunning_fee_cents']) }}.</p>
      </details>
      @endif
    </div>
  </div>
</section>

<!-- Konto-CTA -->
