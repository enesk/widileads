<section class="bg-brand pt-10 pb-14 md:pt-20 md:pb-24">
  <div class="container-portal">
    <div class="grid gap-8 md:grid-cols-[1.2fr_1fr] md:items-center">
      <div class="text-center md:text-left">
        <h1 class="text-3xl md:text-5xl font-bold tracking-tight text-white">Anfragen aus deiner Region. Du zahlst nur bei angenommenem Anruf.</h1>
        <p class="mt-4 text-base md:text-lg text-white/85 max-w-xl mx-auto md:mx-0">Auf unseren Branchenportalen fragen täglich Menschen nach Handwerkern, Ärzten und Dienstleistern. Du siehst die Anfragen, bevor du kaufst.</p>
      </div>
      <svg viewBox="0 0 320 300" class="w-full max-w-xs md:max-w-sm mx-auto" role="img" aria-labelledby="hero-ill-title">
        <title id="hero-ill-title">Smartphone mit eingehender Anfrage, umgeben von Standort-Markierungen</title>
        <circle cx="160" cy="150" r="130" fill="#fff" fill-opacity=".08"/>
        <circle cx="160" cy="150" r="95" fill="#fff" fill-opacity=".08"/>
        <g transform="translate(58 40)">
          <rect x="0" y="0" width="150" height="72" rx="14" fill="#fff"/>
          <circle cx="26" cy="36" r="14" fill="var(--brand-100)"/>
          <path d="M18 43c3-5 13-5 16 0" stroke="var(--brand)" stroke-width="2.5" stroke-linecap="round" fill="none"/>
          <circle cx="26" cy="31" r="5" fill="var(--brand)"/>
          <rect x="50" y="24" width="72" height="8" rx="4" fill="var(--brand)"/>
          <rect x="50" y="40" width="48" height="6" rx="3" fill="var(--brand-100)"/>
          <rect x="112" y="6" width="32" height="14" rx="7" fill="var(--brand)"/>
          <text x="128" y="16.5" font-family="Figtree,system-ui,sans-serif" font-size="9" font-weight="700" fill="#fff" text-anchor="middle">NEU</text>
        </g>
        <g transform="translate(100 96)">
          <rect x="0" y="0" width="120" height="204" rx="22" fill="#fff"/>
          <rect x="8" y="8" width="104" height="188" rx="16" fill="var(--brand-700)"/>
          <rect x="42" y="14" width="36" height="6" rx="3" fill="#fff" fill-opacity=".2"/>
          <circle cx="60" cy="72" r="24" fill="#fff" fill-opacity=".15"/>
          <circle cx="60" cy="72" r="16" fill="#fff"/>
          <path d="M53 85c3-5 11-5 14 0" stroke="var(--brand)" stroke-width="2.5" stroke-linecap="round" fill="none"/>
          <circle cx="60" cy="68" r="5" fill="var(--brand)"/>
          <rect x="30" y="106" width="60" height="7" rx="3.5" fill="#fff"/>
          <rect x="40" y="120" width="40" height="5" rx="2.5" fill="#fff" fill-opacity=".5"/>
          <circle cx="38" cy="166" r="16" fill="#dc2626"/>
          <path d="M31 171c2-6 12-6 14 0" stroke="#fff" stroke-width="2.5" stroke-linecap="round" fill="none"/>
          <circle cx="82" cy="166" r="16" fill="#16a34a"/>
          <path d="M75 165c1 5 6 8 10 7l1-3-3-2-2 1c-2-1-3-2-4-4l1-2-2-3z" fill="#fff"/>
        </g>
        <g fill="#fff">
          <path d="M40 150c0-11 9-20 20-20s20 9 20 20c0 15-20 34-20 34s-20-19-20-34z"/>
          <circle cx="60" cy="150" r="8" fill="var(--brand)"/>
          <path d="M240 190c0-9 7-16 16-16s16 7 16 16c0 12-16 27-16 27s-16-15-16-27z"/>
          <circle cx="256" cy="190" r="6" fill="var(--brand)"/>
          <path d="M250 60c0-7 6-13 13-13s13 6 13 13c0 10-13 22-13 22s-13-12-13-22z"/>
          <circle cx="263" cy="60" r="5" fill="var(--brand)"/>
        </g>
        <g transform="translate(18 214)">
          <rect x="0" y="0" width="90" height="48" rx="12" fill="#fff"/>
          <rect x="12" y="12" width="46" height="7" rx="3.5" fill="var(--brand)"/>
          <rect x="12" y="27" width="66" height="5" rx="2.5" fill="var(--brand-100)"/>
        </g>
      </svg>
    </div>
    <form action="#anfragen" class="card border-0 shadow-lg p-4 md:p-6 mt-10 max-w-3xl mx-auto">
      <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
        <label class="sr-only" for="branche">Branche</label>
        <div class="relative">
          <svg class="size-5 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
          <select id="branche" class="input pl-10 appearance-none">
            <option value="">Branche wählen</option>
            <option>Fliesenleger</option><option>Tierarzt</option><option>Sanitär</option><option>KFZ-Werkstatt</option><option>Maler</option><option>Elektriker</option>
          </select>
          <svg class="size-5 absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <label class="sr-only" for="region">Region</label>
        <div class="relative">
          <svg class="size-5 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
          <input id="region" class="input pl-10" placeholder="PLZ oder Ort" inputmode="numeric">
        </div>
        <button type="submit" class="btn-primary w-full md:w-auto">Anfragen anzeigen</button>
      </div>
      <p class="mt-3 text-sm text-zinc-500 text-center">Kostenlos und ohne Konto. Kontaktdaten siehst du nach dem Kauf.</p>
    </form>
  </div>
</section>

<!-- Aktuelle Anfragen -->
