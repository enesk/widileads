{{--
    Eine Liste ohne Eintraege (Portal Phase 1).

    Der Entwurf zeigt diesen Fall nicht -- eine Liste ist aber oefter leer als
    einem lieb ist, und "nichts" ist ein schlechter Bildschirm. Gebaut aus den
    Bausteinen des Entwurfs: dieselbe Karte, dasselbe Symbol, derselbe Knopf.
--}}
@props(['icon' => 'leads', 'title' => null, 'description' => null])

<x-app.card {{ $attributes->merge(['class' => 'px-6 py-12 flex flex-col items-center text-center gap-3']) }}>
    <span class="size-12 rounded-full bg-zinc-100 flex items-center justify-center">
        <x-app.icon :name="$icon" class="size-6 text-zinc-400" />
    </span>

    <h3 class="text-lg font-semibold text-zinc-900">{{ $title ?? __('portal.empty.title') }}</h3>
    <p class="text-sm text-zinc-500 max-w-prose">{{ $description ?? __('portal.empty.description') }}</p>

    @if (trim($slot) !== '')
        <div class="mt-2">{{ $slot }}</div>
    @endif
</x-app.card>
