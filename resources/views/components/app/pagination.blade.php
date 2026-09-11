{{--
    Blaetterleiste des Portals (Portal Phase 1).

    Die Zahlen des Entwurfs, aber aus dem Paginator statt fest verdrahtet. Die
    Leiste verschwindet, solange es nur eine Seite gibt.

    In einer Livewire-Komponente ist das Blaettern kein Seitenwechsel, sondern
    ein Aufruf: mit :wire="true" rufen die Knoepfe gotoPage() aus WithPagination
    statt einer Adresse zu folgen.
--}}
@props(['paginator', 'wire' => false, 'onEachSide' => 1])

@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, $current - $onEachSide);
        $end = min($last, $current + $onEachSide);
        $base = 'size-11 rounded-xl font-medium flex items-center justify-center';
    @endphp

    <nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-1']) }} aria-label="{{ __('portal.pagination.label') }}">
        @if ($paginator->onFirstPage())
            <span class="btn-ghost px-3 text-zinc-400 pointer-events-none">{{ __('portal.pagination.previous') }}</span>
        @elseif ($wire)
            <button type="button" class="btn-ghost px-3" wire:click="previousPage">{{ __('portal.pagination.previous') }}</button>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn-ghost px-3">{{ __('portal.pagination.previous') }}</a>
        @endif

        @for ($page = $start; $page <= $end; $page++)
            @if ($page === $current)
                <span class="{{ $base }} bg-brand text-white font-semibold" aria-current="page">{{ $page }}</span>
            @elseif ($wire)
                <button type="button" class="{{ $base }} text-zinc-700 hover:bg-zinc-100" wire:click="gotoPage({{ $page }})" aria-label="{{ __('portal.pagination.page', ['page' => $page]) }}">{{ $page }}</button>
            @else
                <a href="{{ $paginator->url($page) }}" class="{{ $base }} text-zinc-700 hover:bg-zinc-100" aria-label="{{ __('portal.pagination.page', ['page' => $page]) }}">{{ $page }}</a>
            @endif
        @endfor

        @if (! $paginator->hasMorePages())
            <span class="btn-ghost px-3 text-zinc-400 pointer-events-none">{{ __('portal.pagination.next') }}</span>
        @elseif ($wire)
            <button type="button" class="btn-ghost px-3" wire:click="nextPage">{{ __('portal.pagination.next') }}</button>
        @else
            <a href="{{ $paginator->nextPageUrl() }}" class="btn-ghost px-3">{{ __('portal.pagination.next') }}</a>
        @endif
    </nav>
@endif
