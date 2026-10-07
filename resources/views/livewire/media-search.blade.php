<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-6">
        <h1 class="font-extra-condensed text-4xl font-extrabold leading-none tracking-tight text-white sm:text-5xl">Cari tontonan</h1>
        <p class="mt-2 text-gray-400">Film, series, dan anime dalam satu pencarian.</p>
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
            class="block w-full rounded-lg border-gray-300 bg-white py-3 pl-10 pr-24 text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-gray-400 focus:ring-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500"
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
                    'bg-perak text-layar' => $type === $value,
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
                <div class="animate-pulse">
                    <div class="aspect-[2/3] w-full rounded-sm bg-gray-800"></div>
                    <div class="mt-2 h-3 w-4/5 rounded bg-gray-800"></div>
                    <div class="mt-1.5 h-3 w-2/5 rounded bg-gray-800"></div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove.delay wire:target="query, type, selectType">
            @if (! $this->hasQuery)
                {{-- State awal: judul yang sedang populer (komponen lazy terpisah) --}}
                <livewire:popular-titles :type="$type === \App\Livewire\MediaSearch::ALL ? null : $type" key="popular-titles" />
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
                {{-- Orang (aktor, sutradara, …) yang namanya cocok; hanya di tab Semua --}}
                @if ($results->people)
                    <section class="mb-8" aria-labelledby="people-heading">
                        <h2 id="people-heading" class="mb-3 text-sm font-semibold text-gray-500 dark:text-gray-400">Orang</h2>

                        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($results->people as $person)
                                <li wire:key="person-{{ $person['id'] }}">
                                    <a href="{{ \App\Services\Media\PersonService::url($person['id'], $person['name_latin'] ?? $person['name']) }}" wire:navigate
                                       class="flex items-center gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200 transition hover:shadow-md dark:bg-gray-800 dark:ring-gray-700">
                                        <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100 text-lg font-semibold text-gray-400 dark:bg-gray-900 dark:text-gray-500">
                                            @if ($person['photo_url'])
                                                <img src="{{ $person['photo_url'] }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                            @else
                                                <span aria-hidden="true">{{ mb_substr($person['name_latin'] ?? $person['name'], 0, 1) }}</span>
                                            @endif
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $person['name_latin'] ?? $person['name'] }}</span>
                                            @if ($person['name_latin'])
                                                <span class="block truncate text-xs text-gray-400 dark:text-gray-500">{{ $person['name'] }}</span>
                                            @endif
                                            @if ($person['department'])
                                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ \App\Services\Media\PersonService::departmentLabel($person['department']) }}</span>
                                            @endif
                                            @if ($person['known_for'])
                                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ implode(', ', $person['known_for']) }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($results->media->isNotEmpty())
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ $results->media->count() }} judul untuk “{{ $query }}”
                    </p>

                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($results->media as $media)
                            <a href="{{ $media->url() }}" wire:navigate wire:key="hit-{{ $media->id }}">
                                <x-media-card :media="$media" />
                            </a>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
