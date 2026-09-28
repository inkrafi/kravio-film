@use('App\Enums\WatchStatus')
@use('App\Livewire\MediaDetail')
@use('App\Services\Media\PersonService')
@use('App\Support\GenreNormalizer')

<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    {{-- Breadcrumb: Cari › Film/Series › Judul --}}
    <nav aria-label="Breadcrumb">
        <ol class="flex min-w-0 items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
            <li class="shrink-0">
                <a href="{{ route('search') }}" wire:navigate class="hover:text-gray-800 dark:hover:text-gray-200">Cari</a>
            </li>
            <li class="shrink-0 text-gray-300 dark:text-gray-600" aria-hidden="true">›</li>
            <li class="shrink-0">
                <a href="{{ route('search', ['tipe' => $media->media_type->value]) }}" wire:navigate class="hover:text-gray-800 dark:hover:text-gray-200">
                    {{ $media->media_type->label() }}
                </a>
            </li>
            <li class="shrink-0 text-gray-300 dark:text-gray-600" aria-hidden="true">›</li>
            <li class="min-w-0">
                <span aria-current="page" class="block truncate font-medium text-gray-900 dark:text-gray-100" title="{{ $media->title }}">
                    {{ $media->title }}
                </span>
            </li>
        </ol>
    </nav>

    <div class="mt-4 grid gap-8 md:grid-cols-[220px_minmax(0,1fr)]">
        {{-- Poster --}}
        <div>
            <div class="aspect-[2/3] w-full overflow-hidden rounded-lg bg-gray-100 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                @if ($media->poster_url)
                    <img src="{{ $media->poster_url }}" alt="Poster {{ $media->title }}" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full items-center justify-center px-3 text-center text-xs text-gray-400">
                        Poster tidak tersedia
                    </div>
                @endif
            </div>

            <button
                type="button"
                wire:click="toggleFavorite"
                @class([
                    'mt-3 w-full rounded-lg px-3 py-2 text-sm font-medium transition',
                    'bg-rose-600 text-white hover:bg-rose-700' => $isFavorite,
                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => ! $isFavorite,
                ])
            >
                {{ $isFavorite ? '♥ Favorit' : '♡ Jadikan Favorit' }}
            </button>

            <p class="mt-1 text-center text-xs text-gray-400 dark:text-gray-500">
                {{ $favoriteCount }}/{{ $maxFavorites }} favorit dipakai
            </p>

            <livewire:add-to-list :media="$media" :key="'add-to-list-'.$media->id" />

            @error('favorite')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Info --}}
        <div>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $media->media_type->badgeClasses() }}">
                {{ $media->media_type->label() }}
            </span>

            <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $media->title }}</h1>

            {{-- Versi latin untuk judul beraksara Korea/Jepang/dll. --}}
            @if ($media->title_latin)
                <p class="text-base font-medium text-gray-700 dark:text-gray-300">{{ $media->title_latin }}</p>
            @endif

            @if ($media->original_title && $media->original_title !== $media->title)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $media->original_title }}
                    @if ($media->original_title_latin && $media->original_title_latin !== $media->title && $media->original_title_latin !== $media->title_latin)
                        <span class="italic">· {{ $media->original_title_latin }}</span>
                    @endif
                </p>
            @endif

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ $media->year ?? 'Tahun tidak diketahui' }}
                · Sumber {{ $media->source->label() }}
            </p>

            {{-- Rating TMDB, IMDb & Rotten Tomatoes; bersama pemain & sutradara diambil setelah halaman tampil --}}
            <div class="mt-3" @if ($detailsPending) wire:init="loadDetails" @endif>
                @if ($detailsPending)
                    <div class="flex gap-1.5" aria-label="Memuat rating, pemain, dan sutradara">
                        <div class="h-7 w-20 animate-pulse rounded-md bg-gray-200 dark:bg-gray-700"></div>
                        <div class="h-7 w-20 animate-pulse rounded-md bg-gray-200 dark:bg-gray-700"></div>
                        <div class="h-7 w-16 animate-pulse rounded-md bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                @else
                    <x-external-ratings :media="$media" />

                    @if ($media->imdbUrl())
                        <a href="{{ $media->imdbUrl() }}" target="_blank" rel="noopener"
                           class="mt-1 inline-block text-xs text-gray-500 underline-offset-2 hover:underline dark:text-gray-400">
                            Lihat di IMDb ↗
                        </a>
                    @endif
                @endif
            </div>

            @if ($media->displayGenres())
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($media->displayGenres() as $genre)
                        <a href="{{ route('genre.show', ['slug' => GenreNormalizer::slug($genre), 'tipe' => $media->media_type->value]) }}" wire:navigate
                           class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600 hover:bg-indigo-100 hover:text-indigo-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-indigo-900/40 dark:hover:text-indigo-300">
                            {{ $genre }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Sinopsis: terjemahan Indonesia kalau ada, teks asli bisa dibuka --}}
            @if ($translationPending)
                <div class="mt-4 space-y-2" aria-label="Menerjemahkan sinopsis">
                    <div class="h-3 w-full animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-3 w-11/12 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-3 w-3/4 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            @elseif ($media->hasTranslatedSynopsis())
                <div class="mt-4" x-data="{ original: false }">
                    <p x-show="! original" class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $media->synopsis_id }}</p>
                    <p x-show="original" x-cloak class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $media->synopsis }}</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        <span x-text="original ? 'Teks asli' : 'Diterjemahkan otomatis'">Diterjemahkan otomatis</span> ·
                        <button type="button" x-on:click="original = ! original" class="underline-offset-2 hover:underline"
                                x-text="original ? 'Lihat terjemahan' : 'Lihat teks asli'">Lihat teks asli</button>
                    </p>
                </div>
            @else
                <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                    {{ $media->displaySynopsis() ?: 'Sinopsis belum tersedia untuk judul ini.' }}
                </p>
            @endif

            {{-- Sutradara & kreator --}}
            @if ($detailsPending)
                <div class="mt-4 h-4 w-1/2 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
            @else
                @php
                    $crew = array_filter([
                        'Sutradara' => $media->people('directors'),
                        'Kreator' => $media->people('creators'),
                    ]);
                @endphp

                @if ($crew)
                    <dl class="mt-4 space-y-1 text-sm">
                        @foreach ($crew as $label => $people)
                            <div class="flex gap-2">
                                <dt class="w-20 shrink-0 text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="text-gray-900 dark:text-gray-100">
                                    @foreach ($people as $person)
                                        @php $label = filled($person['name_latin'] ?? null) ? $person['name_latin'].' ('.$person['name'].')' : $person['name']; @endphp
                                        @if ($person['id'] ?? null)
                                            <a href="{{ PersonService::url($person['id'], $person['name_latin'] ?? $person['name']) }}" wire:navigate
                                               class="hover:text-indigo-600 hover:underline dark:hover:text-indigo-400">{{ $label }}</a>@if (! $loop->last), @endif
                                        @else
                                            {{ $label }}@if (! $loop->last), @endif
                                        @endif
                                    @endforeach
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            @endif

            {{-- Tombol watched / watchlist --}}
            <div class="mt-6 flex flex-wrap gap-2">
                <button
                    type="button"
                    wire:click="setStatus('{{ WatchStatus::Watched->value }}')"
                    @class([
                        'rounded-lg px-4 py-2 text-sm font-medium transition',
                        'bg-emerald-600 text-white hover:bg-emerald-700' => $status === WatchStatus::Watched->value,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $status !== WatchStatus::Watched->value,
                    ])
                >
                    {{ $status === WatchStatus::Watched->value ? '✓ Sudah Ditonton' : 'Tandai Sudah Ditonton' }}
                </button>

                <button
                    type="button"
                    wire:click="setStatus('{{ WatchStatus::Watchlist->value }}')"
                    @class([
                        'rounded-lg px-4 py-2 text-sm font-medium transition',
                        'bg-indigo-600 text-white hover:bg-indigo-700' => $status === WatchStatus::Watchlist->value,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => $status !== WatchStatus::Watchlist->value,
                    ])
                >
                    {{ $status === WatchStatus::Watchlist->value ? '✓ Di Watchlist' : 'Tambah ke Watchlist' }}
                </button>

                @if ($status)
                    <button
                        type="button"
                        wire:click="removeEntry"
                        class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    >
                        Hapus dari daftar
                    </button>
                @endif
            </div>

            {{-- Rating 1-10 --}}
            <div class="mt-6">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Rating kamu</h2>
                    @if ($rating)
                        <button type="button" wire:click="clearRating"
                                class="text-xs text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                            Hapus rating
                        </button>
                    @endif
                </div>

                <div class="mt-2 flex flex-wrap gap-1.5" role="group" aria-label="Beri rating 1 sampai 10">
                    @for ($value = MediaDetail::MIN_RATING; $value <= MediaDetail::MAX_RATING; $value++)
                        <button
                            type="button"
                            wire:click="rate({{ $value }})"
                            aria-pressed="{{ $rating === $value ? 'true' : 'false' }}"
                            @class([
                                'h-9 w-9 rounded-md text-sm font-semibold transition',
                                'bg-amber-500 text-white' => $rating !== null && $value <= $rating,
                                'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $rating === null || $value > $rating,
                            ])
                        >
                            {{ $value }}
                        </button>
                    @endfor
                </div>

                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    {{ $rating ? "Kamu memberi $rating dari 10." : 'Belum dinilai. Memberi rating otomatis menandai judul ini sudah ditonton.' }}
                </p>
            </div>

            {{-- Review --}}
            <div class="mt-8 border-t border-gray-200 pt-6 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Review kamu</h2>

                @if ($editingReview)
                    <form wire:submit="saveReview" class="mt-3">
                        <textarea
                            wire:model="form.body"
                            rows="5"
                            placeholder="Apa yang kamu pikirkan soal judul ini?"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        ></textarea>
                        @error('form.body')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror

                        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" wire:model="form.contains_spoiler"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900">
                            Review ini mengandung spoiler
                        </label>

                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Simpan review
                            </button>
                            <button type="button" wire:click="cancelReview"
                                    class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                                Batal
                            </button>
                        </div>
                    </form>
                @elseif ($review)
                    <div class="mt-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800/60">
                        @if ($review->contains_spoiler)
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">
                                Mengandung spoiler
                            </p>
                        @endif

                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $review->body }}</p>

                        <div class="mt-3 flex gap-3 text-xs">
                            <button type="button" wire:click="editReview" class="text-indigo-600 hover:underline dark:text-indigo-400">Ubah</button>
                            <button type="button" wire:click="deleteReview" class="text-gray-500 hover:underline dark:text-gray-400">Hapus</button>
                        </div>
                    </div>
                @else
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Kamu belum menulis review untuk judul ini.</p>
                    <button type="button" wire:click="editReview"
                            class="mt-2 rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        Tulis review
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Pemain --}}
    @if ($detailsPending)
        <div class="mt-10 grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-6">
            @for ($i = 0; $i < 6; $i++)
                <div class="animate-pulse space-y-2">
                    <div class="aspect-[2/3] rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-3 w-4/5 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            @endfor
        </div>
    @elseif ($media->people('cast'))
        <section class="mt-10" aria-labelledby="cast-heading">
            <h2 id="cast-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pemain</h2>

            <ul class="mt-3 grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-6">
                @foreach ($media->people('cast') as $person)
                    <li class="relative">
                        @if ($person['id'] ?? null)
                            {{-- Seluruh kartu bisa diklik ke halaman orang --}}
                            <a href="{{ PersonService::url($person['id'], $person['name_latin'] ?? $person['name']) }}" wire:navigate
                               class="absolute inset-0 z-10 rounded-lg" aria-label="Lihat {{ $person['name_latin'] ?? $person['name'] }}"></a>
                        @endif
                        <div class="aspect-[2/3] overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                            @if ($person['photo_url'])
                                <img src="{{ $person['photo_url'] }}" alt="Foto {{ $person['name'] }}" loading="lazy" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full items-center justify-center text-2xl font-semibold text-gray-400 dark:text-gray-500" aria-hidden="true">
                                    {{ mb_substr($person['name'], 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <p class="mt-1.5 text-sm font-medium leading-tight text-gray-900 dark:text-gray-100">{{ $person['name_latin'] ?? null ?: $person['name'] }}</p>
                        @if (filled($person['name_latin'] ?? null))
                            <p class="text-xs leading-tight text-gray-400 dark:text-gray-500">{{ $person['name'] }}</p>
                        @endif
                        @if ($person['role'])
                            <p class="text-xs leading-tight text-gray-500 dark:text-gray-400">{{ \App\Support\RoleLabel::translate($person['role']) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
