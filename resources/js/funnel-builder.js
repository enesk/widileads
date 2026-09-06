import Sortable from 'sortablejs'

/**
 * Alpine-Komponente fuer die sortierbaren Listen im Funnel-Builder (FB-015).
 *
 * Sie meldet nach dem Ziehen die neue Reihenfolge der Kennungen an Livewire.
 * Die Reihenfolge wird serverseitig gegen die tatsaechlichen Datensaetze
 * gefiltert - der Browser bestimmt nur die Anordnung, nicht die Menge.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('funnelSortable', ({ onSort }) => ({
        sortable: null,

        init(element) {
            this.sortable = Sortable.create(element, {
                animation: 150,
                handle: '[data-drag-handle]',
                ghostClass: 'opacity-40',
                onEnd: () => onSort(this.orderedIds(element)),
            })

            // Livewire ersetzt die Liste nach jedem Rendern; die Instanz muss
            // dann mit aufgeraeumt werden, sonst haengen mehrere Sortables am
            // selben Element.
            this.$cleanup?.(() => this.sortable?.destroy())
        },

        orderedIds(element) {
            return Array.from(element.querySelectorAll('[data-id]')).map((node) => node.dataset.id)
        },
    }))
})
