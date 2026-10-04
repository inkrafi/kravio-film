<section aria-label="Memuat judul populer">
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="popular-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Sedang populer minggu ini</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">Atau ketik minimal {{ \App\Livewire\MediaSearch::MIN_QUERY_LENGTH }} huruf untuk mencari.</p>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
        @for ($i = 0; $i < 12; $i++)
            <div class="animate-pulse overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <div class="aspect-[2/3] w-full bg-gray-200 dark:bg-gray-700"></div>
                <div class="space-y-2 p-3">
                    <div class="h-3 w-4/5 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-3 w-2/5 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
        @endfor
    </div>
</section>
