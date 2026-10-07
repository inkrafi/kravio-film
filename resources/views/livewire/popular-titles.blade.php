<section aria-labelledby="popular-heading">
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="popular-heading" class="font-condensed text-2xl font-bold text-gray-100">Sedang populer minggu ini</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400">Atau ketik minimal {{ \App\Livewire\MediaSearch::MIN_QUERY_LENGTH }} huruf untuk mencari.</p>
    </div>

    @if ($popular['media']->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 px-6 py-16 text-center dark:border-gray-700">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Mulai ketik untuk mencari</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Daftar populer sedang tidak bisa dimuat. Coba “Interstellar”, “Breaking Bad”, atau “Frieren”.
            </p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            @foreach ($popular['media'] as $media)
                <a href="{{ $media->url() }}" wire:navigate wire:key="popular-{{ $media->id }}">
                    <x-media-card :media="$media" />
                </a>
            @endforeach
        </div>

        @if ($popular['failed'])
            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                {{ \App\Enums\MediaSource::labels($popular['failed']) }} sedang tidak bisa dihubungi, jadi daftarnya belum lengkap.
            </p>
        @endif
    @endif
</section>
