<section aria-label="Memuat judul populer">
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="popular-heading" class="font-condensed text-2xl font-bold text-gray-100">Sedang populer minggu ini</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">Atau ketik minimal {{ \App\Livewire\MediaSearch::MIN_QUERY_LENGTH }} huruf untuk mencari.</p>
    </div>

    <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
        @for ($i = 0; $i < 12; $i++)
            <div class="animate-pulse">
                    <div class="aspect-[2/3] w-full rounded-sm bg-gray-800"></div>
                    <div class="mt-2 h-3 w-4/5 rounded bg-gray-800"></div>
                    <div class="mt-1.5 h-3 w-2/5 rounded bg-gray-800"></div>
                </div>
        @endfor
    </div>
</section>
