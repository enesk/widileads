{{--
    Navigationsspalte des Portals (Portal Phase 1).

    Einmal geschrieben, zweimal eingesetzt: in der Schublade fuer schmale
    Bildschirme und in der festen Spalte daneben. Der Entwurf hat beide Listen
    ausgeschrieben -- bei zwei Kopien laeuft die eine der anderen davon.
--}}
@props(['navigation', 'tenant', 'role' => null])

<x-app.workspace-switcher :tenant="$tenant" :role="$role" />

<nav class="p-4 space-y-6 text-sm" aria-label="{{ __('portal.nav.label') }}">
    @foreach ($navigation as $section)
        <div>
            @if ($section['label'])
                <p class="px-3 mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ $section['label'] }}</p>
            @endif

            <div class="space-y-1">
                @foreach ($section['items'] as $item)
                    <x-app.nav-link
                        :label="$item['label']"
                        :icon="$item['icon']"
                        :url="$item['url']"
                        :active="request()->routeIs($item['route'])"
                    />
                @endforeach
            </div>
        </div>
    @endforeach
</nav>
