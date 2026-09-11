{{--
    Die Zeile ueber einer Liste (Portal Phase 1).

    Links steht, wie viele Datensaetze die Liste zeigt, rechts stehen Filter und
    Sortierung. Auf schmalen Bildschirmen untereinander, sonst nebeneinander --
    im Entwurf wiederholt sich dieser Aufbau auf jeder Listenseite.
--}}
@props(['summary' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3']) }}>
    <p class="text-sm text-zinc-500">{{ $summary }}</p>

    <div class="flex flex-col sm:flex-row gap-2">
        {{ $slot }}
    </div>
</div>
