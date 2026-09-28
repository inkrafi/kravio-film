import Sortable from 'sortablejs';

/**
 * x-data="sortableList('moveTo')": seret-lepas urutan anak elemen ini, lalu
 * laporkan ke Livewire lewat $wire[method](id, posisiBaru). Setiap anak wajib
 * punya data-sort-id; pegangan seretnya elemen [data-sort-handle].
 *
 * Alpine sudah dibawa Livewire, jadi komponen didaftarkan saat alpine:init.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('sortableList', (method = 'moveTo') => ({
        sortable: null,

        init() {
            this.sortable = Sortable.create(this.$el, {
                handle: '[data-sort-handle]',
                draggable: '[data-sort-id]',
                animation: 150,
                ghostClass: 'opacity-40',
                chosenClass: 'shadow-lg',
                // Di layar sentuh, tahan sebentar supaya menggulir tidak dianggap menyeret.
                delay: 150,
                delayOnTouchOnly: true,
                onEnd: (event) => {
                    if (event.oldIndex === event.newIndex) {
                        return;
                    }

                    this.$wire[method](Number(event.item.dataset.sortId), event.newIndex + 1);
                },
            });
        },

        destroy() {
            this.sortable?.destroy();
        },
    }));
});
