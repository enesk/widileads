{{--
    Kopf der oeffentlichen Portalseiten.

    Die Abschnittsanker werden absolut ueber route('home') gebildet, weil der
    Kopf auch auf Seiten ohne diese Abschnitte erscheint (Login, Impressum,
    Datenschutz) -- genau wie im Fuss.
--}}
<header class="sticky top-0 z-40 bg-white border-b border-zinc-200">
  <div class="container-portal flex items-center justify-between h-16">
    <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-lg text-zinc-900">
      <span class="size-8 rounded-lg bg-brand text-white flex items-center justify-center">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </span>
      widileads
    </a>
    <nav class="hidden md:flex items-center gap-2">
      <a href="{{ route('home') }}/#so-funktionierts" class="btn-ghost">So funktioniert's</a>
      <a href="{{ route('home') }}/#preis" class="btn-ghost">Preis</a>
      <a href="{{ route('login') }}" class="btn-ghost">Anmelden</a>
      <a href="{{ route('buyer.register') }}" class="btn-primary">Konto erstellen</a>
    </nav>
    <button class="md:hidden btn-ghost px-3" aria-label="Menü öffnen" aria-expanded="false" data-menu-toggle="menu">
      <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
  </div>
  <div id="menu" class="hidden md:hidden border-t border-zinc-200 bg-white">
    <div class="container-portal py-3 flex flex-col gap-1">
      <a href="{{ route('home') }}/#so-funktionierts" class="btn-ghost justify-start">So funktioniert's</a>
      <a href="{{ route('home') }}/#preis" class="btn-ghost justify-start">Preis</a>
      <a href="{{ route('home') }}/#faq" class="btn-ghost justify-start">Fragen und Antworten</a>
      <a href="{{ route('login') }}" class="btn-secondary mt-2">Anmelden</a>
      <a href="{{ route('buyer.register') }}" class="btn-primary">Konto erstellen</a>
    </div>
  </div>
</header>

<!-- Hero -->
