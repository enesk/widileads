{{--
    Der Workspace-Knopf ueber der Navigation (Portal Phase 1, Wechsel aus
    Ticket #6).

    Der Wechsel ist ein gewoehnlicher Verweis auf dieselbe Seite mit der
    anderen UUID -- der Pfad bleibt die einzige Quelle des Mandantenkontexts.
    Die Adresse baut App\Support\PortalNavigation::switchUrl().

    Gehoert der Nutzer nur zu einem Workspace, bleibt der Baustein eine Anzeige
    ohne Knopf: ein Menue mit genau dem Eintrag, in dem man schon steht, waere
    ein Klick ins Leere.
--}}
@props(['tenant', 'role' => null])

@php
    $initials = fn (string $name): string => collect(preg_split('/\s+/', trim($name)))
        // Nur Woerter, die mit einem Buchstaben anfangen: aus "Schoder & Altuntas"
        // wird sonst "S&" statt "SA".
        ->filter(fn (string $part) => preg_match('/^\p{L}/u', $part) === 1)
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $roleOf = fn ($workspace): string => $workspace->isBuyer()
        ? __('portal.workspace.buyer')
        : __('portal.workspace.seller');

    $name = $tenant->name ?? '';
    // Ueber die Beziehung und nicht ueber eine eigene Abfrage: Die Navigation
    // steht zweimal auf der Seite (Schublade und feste Spalte), die geladene
    // Beziehung wird beim zweiten Mal wiederverwendet.
    $workspaces = (auth()->user()?->tenants ?? collect())->sortBy('name');
@endphp

<div class="p-4 border-b border-zinc-200">
    @if ($workspaces->count() < 2)
        <div class="w-full flex items-center gap-3 min-h-11 px-2">
            <span class="size-9 rounded-lg bg-brand text-white font-semibold flex items-center justify-center text-sm" aria-hidden="true">{{ $initials($name) }}</span>
            <span class="flex-1 min-w-0">
                <span class="block font-semibold text-zinc-900 truncate">{{ $name }}</span>
                <span class="block text-xs text-zinc-500">{{ $role }}</span>
            </span>
        </div>
    @else
        <x-app.dropdown width="w-full" :label="__('portal.workspace.switch')">
            <x-slot:trigger class="w-full flex items-center gap-3 min-h-11 px-2 rounded-xl hover:bg-zinc-100 text-left">
                <span class="size-9 rounded-lg bg-brand text-white font-semibold flex items-center justify-center text-sm" aria-hidden="true">{{ $initials($name) }}</span>
                <span class="flex-1 min-w-0">
                    <span class="block font-semibold text-zinc-900 truncate">{{ $name }}</span>
                    <span class="block text-xs text-zinc-500">{{ $role }}</span>
                </span>
                <x-app.icon name="chevron-up-down" class="size-5 text-zinc-400" />
            </x-slot:trigger>

            <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">{{ __('portal.workspace.switch') }}</p>

            @foreach ($workspaces as $workspace)
                @php($isCurrent = $workspace->is($tenant))

                <a href="{{ \App\Support\PortalNavigation::switchUrl($workspace) }}"
                   role="menuitem"
                   @class([
                       'flex items-center gap-3 min-h-11 px-3 rounded-lg text-sm',
                       'bg-brand-50 text-brand font-semibold' => $isCurrent,
                       'text-zinc-700 hover:bg-zinc-100' => ! $isCurrent,
                   ])
                   @if ($isCurrent) aria-current="true" @endif>
                    <span class="size-7 rounded-lg bg-zinc-100 text-zinc-600 font-semibold flex items-center justify-center text-xs" aria-hidden="true">{{ $initials($workspace->name ?? '') }}</span>
                    <span class="flex-1 min-w-0">
                        <span class="block truncate">{{ $workspace->name }}</span>
                        <span class="block text-xs font-normal text-zinc-500">{{ $roleOf($workspace) }}</span>
                    </span>
                </a>
            @endforeach
        </x-app.dropdown>
    @endif
</div>
