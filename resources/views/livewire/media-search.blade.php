<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Cari tontonan</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Film dan series dari TMDB, anime dari MyAnimeList — dalam satu kolom pencarian.
        </p>
    </header>

    {{-- Search bar --}}
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3m1.8-4.45a6.25 6.25 0 1 1-12.5 0 6.25 6.25 0 0 1 12.5 0Z" />
            </svg>
        </span>

        <input
            type="search"
            wire:model.live.debounce.400ms="query"
            placeholder="Judul film, series, atau anime…"
            autofocus
            aria-label="Kata kunci pencarian"
            class="block w-full rounded-lg border-gray-300 bg-white py-3 pl-10 pr-24 text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500"
        >

        <div class="absolute inset-y-0 right-0 flex items-center gap-2 pr-3">
            <span wire:loading wire:target="query, type, selectType" class="text-xs text-gray-400">
                mencari…
            </span>

            @if ($query !== '')
                <button
                    type="button"
                    wire:click="clear"
                    class="rounded px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                >
                    Bersihkan
                </button>
            @endif
        </div>
    </div>

    {{-- Tab filter --}}
    <div class="mt-4 flex flex-wrap gap-2" role="tablist" aria-label="Filter tipe media">
        @foreach ($this->tabs as $value => $label)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $type === $value ? 'true' : 'false' }}"
                wire:click="selectType('{{ $value }}')"
                @class([
                    'rounded-full px-4 py-1.5 text-sm font-medium transition',
                    'bg-indigo-600 text-white' => $type === $value,
                    'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $type !== $value,
                ])
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Catatan kondisi sumber data --}}
    @if ($results->usedFallback())
        <div class="mt-4 rounded-lg border border-sky-300 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-700/60 dark:bg-sky-900/20 dark:text-sky-200">
            @foreach ($results->fallbackSources as $down => $substitute)
                <p>
                    {{ \App\Enums\MediaSource::from($down)->label() }} sedang bermasalah, jadi hasilnya diambil dari
                    {{ \App\Enums\MediaSource::from($substitute)->label() }}.
                </p>
            @endforeach
        </div>
    @endif

    @if ($results->hasProblems())
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-200">
            @if ($results->failedSources)
                <p>
                    Sebagian sumber sedang tidak bisa dihubungi
                    ({{ \App\Enums\MediaSource::labels($results->failedSources) }}), jadi hasilnya mungkin belum lengkap.
                </p>
            @endif
            @if ($results->skippedSources)
                <p>
                    Sumber {{ \App\Enums\MediaSource::labels($results->skippedSources) }} dilewati karena
                    API key-nya belum diisi di <code>.env</code>.
                </p>
            @endif
        </div>
    @endif

    {{-- Hasil --}}
    <div class="mt-6">
        {{-- Skeleton saat memuat --}}
        <div wire:loading.delay wire:target="query, type, selectType" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
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

        <div wire:loading.remove.delay wire:target="query, type, selectType">
            @if (! $this->hasQuery)
                {{-- State awal --}}
                <div class="rounded-lg border border-dashed border-gray-300 px-6 py-16 text-center dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Mulai ketik untuk mencari</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Minimal {{ $minQueryLength }} huruf. Coba “Interstellar”, “Breaking Bad”, atau “Frieren”.
                    </p>
                </div>
            @elseif ($results->isEmpty())
                {{-- State kosong --}}
                <div class="rounded-lg border border-dashed border-gray-300 px-6 py-16 text-center dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Tidak ditemukan hasil untuk “{{ $query }}”
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Coba kata kunci lain, judul aslinya, atau ganti tab filter.
                    </p>
                </div>
            @else
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ $results->media->count() }} hasil untuk “{{ $query }}”
                </p>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($results->media as $media)
                        <x-media-card :media="$media" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
