{{--
    Fuss der oeffentlichen Portalseiten.

    Alle Verweise zeigen auf vorhandene Ziele. Die Abschnittsanker werden
    absolut ueber route('home') gebildet, weil der Fuss auch auf Seiten ohne
    diese Abschnitte erscheint (Login, Impressum).

    Das Impressum erscheint erst, wenn die Firmenstammdaten in den
    Rechnungseinstellungen gepflegt sind (App\Services\CompanyProfile) -- ein
    Verweis ins Leere waere schlechter als keiner.
--}}
@inject('company', 'App\Services\CompanyProfile')
<footer class="bg-white border-t border-zinc-200">
  <div class="container-portal py-10 grid gap-8 grid-cols-2 md:grid-cols-3 text-sm">
    <div><h4 class="font-semibold text-zinc-900 mb-3">widileads</h4><ul class="space-y-2 text-zinc-500"><li><a href="{{ route('home') }}/#anfragen" class="hover:text-zinc-900">Anfragen ansehen</a></li><li><a href="{{ route('home') }}/#so-funktionierts" class="hover:text-zinc-900">So funktioniert's</a></li><li><a href="{{ route('home') }}/#preis" class="hover:text-zinc-900">Preis</a></li><li><a href="{{ route('home') }}/#faq" class="hover:text-zinc-900">Fragen und Antworten</a></li></ul></div>
    <div><h4 class="font-semibold text-zinc-900 mb-3">Für Käufer</h4><ul class="space-y-2 text-zinc-500"><li><a href="{{ route('login') }}" class="hover:text-zinc-900">Anmelden</a></li><li><a href="{{ route('buyer.register') }}" class="hover:text-zinc-900">Konto erstellen</a></li><li><a href="{{ route('home') }}/#faq" class="hover:text-zinc-900">Guthaben</a></li><li><a href="{{ route('home') }}/#preis" class="hover:text-zinc-900">Abrechnungsregeln</a></li></ul></div>
    <div><h4 class="font-semibold text-zinc-900 mb-3">Rechtliches</h4><ul class="space-y-2 text-zinc-500">
      @if ($company->isComplete())
        <li><a href="{{ route('imprint') }}" class="hover:text-zinc-900">Impressum</a></li>
      @endif
      <li><a href="{{ route('privacy-policy') }}" class="hover:text-zinc-900">Datenschutz</a></li><li><a href="{{ route('terms-of-service') }}" class="hover:text-zinc-900">AGB</a></li></ul></div>
  </div>
  <div class="border-t border-zinc-200">
    <div class="container-portal py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-zinc-500">
      <span>© 2026 widileads</span>
      <span>Ein Angebot von Enes Kul – Webentwicklung &amp; IT-Dienstleistungen</span>
    </div>
  </div>
</footer>
