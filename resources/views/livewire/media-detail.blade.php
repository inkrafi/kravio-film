@use('App\Enums\WatchStatus')
@use('App\Livewire\MediaDetail')
@use('App\Services\Media\PersonService')
@use('App\Support\GenreNormalizer')

<div>
    {{-- Hero: adegan dari judul ini, judul seperti di poster, dan satu kalimat orang sebagai subtitle --}}
    <section class="relative isolate overflow-hidden border-b border-gray-800">
        @if ($media->heroBackdropUrl())
            <img src="{{ $media->heroBackdropUrl() }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover object-top">
            {{-- Menggelapkan adegan supaya judul dan subtitle tetap terbaca --}}
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-layar via-layar/80 to-layar/50"></div>
        @endif

        <div class="mx-auto max-w-5xl px-4 pb-8 pt-5 sm:px-6 lg:px-8">
            <nav aria-label="Breadcrumb">
                <ol class="flex min-w-0 items-center gap-1.5 text-sm text-gray-400">
                    <li class="shrink-0"><a href="{{ route('search') }}" wire:navigate class="hover:text-white">Cari</a></li>
                    <li class="shrink-0 text-gray-600" aria-hidden="true">/</li>
                    <li class="shrink-0">
                        <a href="{{ route('search', ['tipe' => $media->media_type->value]) }}" wire:navigate class="hover:text-white">{{ $media->media_type->label() }}</a>
                    </li>
                </ol>
            </nav>

            <div @class(['flex items-end gap-5 sm:gap-8', 'mt-24 sm:mt-40' => $media->heroBackdropUrl(), 'mt-8' => ! $media->heroBackdropUrl()])>
                <div class="w-28 shrink-0 sm:w-44">
                    <div class="aspect-[2/3] overflow-hidden rounded-sm bg-kursi shadow-2xl shadow-black/60">
                        @if ($media->poster_url)
                            <img src="{{ $media->poster_url }}" alt="Poster {{ $media->title }}" class="h-full w-full object-cover">
                        @endif
                    </div>
                </div>

                <div class="min-w-0 pb-1">
                    <h1 class="font-extra-condensed text-4xl font-extrabold leading-[0.95] tracking-tight text-white sm:text-6xl lg:text-7xl">{{ $media->title }}</h1>

                    {{-- Versi latin untuk judul beraksara Korea/Jepang/dll. --}}
                    @if ($media->title_latin)
                        <p class="mt-2 text-lg font-medium text-gray-200">{{ $media->title_latin }}</p>
                    @endif

                    @if ($media->original_title && $media->original_title !== $media->title)
                        <p class="mt-1 text-sm text-gray-400">
                            {{ $media->original_title }}
                            @if ($media->original_title_latin && $media->original_title_latin !== $media->title && $media->original_title_latin !== $media->title_latin)
                                <span class="italic">({{ $media->original_title_latin }})</span>
                            @endif
                        </p>
                    @endif

                    <p class="mt-3 text-sm text-gray-300">
                        {{ $media->media_type->label() }}, {{ $media->year ?? 'tahun tidak diketahui' }}
                    </p>

                    @if ($media->displayGenres())
                        <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                            @foreach ($media->displayGenres() as $genre)
                                <li>
                                    <a href="{{ route('genre.show', ['slug' => GenreNormalizer::slug($genre), 'tipe' => $media->media_type->value]) }}" wire:navigate
                                       class="text-gray-300 underline decoration-gray-600 underline-offset-4 hover:text-white hover:decoration-gray-300">{{ $genre }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            @if ($subtitle)
                <figure class="mx-auto mt-10 max-w-3xl text-center">
                    <blockquote class="subtitle text-lg leading-snug sm:text-2xl">{{ $subtitle['body'] }}</blockquote>
                    <figcaption class="subtitle mt-1 text-sm font-medium">
                        ({{ $subtitle['name'] }}@if ($subtitle['rating']), ★ {{ $subtitle['rating'] }}@endif)
                    </figcaption>
                </figure>
            @endif
        </div>
    </section>

    <div class="mx-auto max-w-5xl px-4 pb-16 sm:px-6 lg:px-8">
        <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_16rem]">
            {{-- Kolom utama: yang dilakukan pengguna terhadap judul ini --}}
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="setStatus('{{ WatchStatus::Watched->value }}')"
                        aria-pressed="{{ $status === WatchStatus::Watched->value ? 'true' : 'false' }}"
                        @class([
                            'rounded-md px-4 py-2 text-sm font-semibold transition',
                            'bg-perak text-layar hover:bg-white' => $status === WatchStatus::Watched->value,
                            'bg-gray-800 text-gray-200 hover:bg-gray-700' => $status !== WatchStatus::Watched->value,
                        ])
                    >
                        {{ $status === WatchStatus::Watched->value ? '✓ Sudah ditonton' : 'Tandai sudah ditonton' }}
                    </button>

                    <button
                        type="button"
                        wire:click="setStatus('{{ WatchStatus::Watchlist->value }}')"
                        aria-pressed="{{ $status === WatchStatus::Watchlist->value ? 'true' : 'false' }}"
                        @class([
                            'rounded-md px-4 py-2 text-sm font-semibold transition',
                            'bg-perak text-layar hover:bg-white' => $status === WatchStatus::Watchlist->value,
                            'bg-gray-800 text-gray-200 hover:bg-gray-700' => $status !== WatchStatus::Watchlist->value,
                        ])
                    >
                        {{ $status === WatchStatus::Watchlist->value ? '✓ Di watchlist' : 'Simpan ke watchlist' }}
                    </button>

                    <button
                        type="button"
                        wire:click="toggleFavorite"
                        aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
                        title="{{ $favoriteCount }} dari {{ $maxFavorites }} slot favorit terpakai"
                        @class([
                            'rounded-md px-4 py-2 text-sm font-semibold transition',
                            'bg-kredit text-white hover:brightness-110' => $isFavorite,
                            'bg-gray-800 text-gray-200 hover:bg-gray-700' => ! $isFavorite,
                        ])
                    >
                        {{ $isFavorite ? '♥ Favorit' : '♡ Favorit' }}
                    </button>

                    <livewire:add-to-list :media="$media" :key="'add-to-list-'.$media->id" />

                    @if ($status)
                        <button type="button" wire:click="removeEntry" class="px-2 py-2 text-sm text-gray-400 underline-offset-4 hover:text-gray-100 hover:underline">
                            Hapus dari daftar
                        </button>
                    @endif
                </div>

                @error('favorite')
                    <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                @enderror

                {{-- Rating 1–10: skala yang terisi sampai nilai yang dipilih --}}
                <div class="mt-8">
                    <div class="flex items-baseline gap-3">
                        <h2 class="text-sm font-semibold text-gray-100">Rating kamu</h2>
                        @if ($rating)
                            <button type="button" wire:click="clearRating" class="text-xs text-gray-400 underline-offset-4 hover:text-gray-100 hover:underline">
                                Hapus rating
                            </button>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center gap-4">
                        <div class="flex gap-1" role="group" aria-label="Beri rating 1 sampai 10">
                            @for ($value = MediaDetail::MIN_RATING; $value <= MediaDetail::MAX_RATING; $value++)
                                <button
                                    type="button"
                                    wire:click="rate({{ $value }})"
                                    aria-pressed="{{ $rating === $value ? 'true' : 'false' }}"
                                    aria-label="Rating {{ $value }}"
                                    title="{{ $value }}"
                                    @class([
                                        'h-8 w-4 rounded-sm transition-colors sm:w-5',
                                        'bg-subtitle' => $rating !== null && $value <= $rating,
                                        'bg-gray-700 hover:bg-gray-500' => $rating === null || $value > $rating,
                                    ])
                                ></button>
                            @endfor
                        </div>

                        <p class="font-condensed text-3xl font-bold leading-none text-subtitle" aria-live="polite">
                            @if ($rating)
                                {{ $rating }}<span class="text-base font-medium text-gray-500">/10</span>
                            @endif
                        </p>
                    </div>

                    @unless ($rating)
                        <p class="mt-2 text-xs text-gray-500">Memberi rating otomatis menandai judul ini sudah ditonton.</p>
                    @endunless
                </div>

                {{-- Sinopsis: terjemahan Indonesia kalau ada, teks asli bisa dibuka --}}
                <div class="mt-8 max-w-prose">
                    @if ($translationPending)
                        <div class="space-y-2" aria-label="Menerjemahkan sinopsis">
                            <div class="h-3 w-full animate-pulse rounded bg-gray-800"></div>
                            <div class="h-3 w-11/12 animate-pulse rounded bg-gray-800"></div>
                            <div class="h-3 w-3/4 animate-pulse rounded bg-gray-800"></div>
                        </div>
                    @elseif ($media->hasTranslatedSynopsis())
                        <div x-data="{ original: false }">
                            <p x-show="! original" class="whitespace-pre-line leading-relaxed text-gray-300">{{ $media->synopsis_id }}</p>
                            <p x-show="original" x-cloak class="whitespace-pre-line leading-relaxed text-gray-300">{{ $media->synopsis }}</p>
                            <p class="mt-2 text-xs text-gray-500">
                                <span x-text="original ? 'Teks asli.' : 'Diterjemahkan otomatis.'">Diterjemahkan otomatis.</span>
                                <button type="button" x-on:click="original = ! original" class="underline underline-offset-4 hover:text-gray-200"
                                        x-text="original ? 'Lihat terjemahan' : 'Lihat teks asli'">Lihat teks asli</button>
                            </p>
                        </div>
                    @else
                        <p class="whitespace-pre-line leading-relaxed text-gray-300">
                            {{ $media->displaySynopsis() ?: 'Sinopsis belum tersedia untuk judul ini.' }}
                        </p>
                    @endif
                </div>

                {{-- Sutradara & kreator --}}
                @if ($detailsPending)
                    <div class="mt-6 h-4 w-1/2 animate-pulse rounded bg-gray-800"></div>
                @else
                    @php
                        $crew = array_filter([
                            'Sutradara' => $media->people('directors'),
                            'Kreator' => $media->people('creators'),
                        ]);
                    @endphp

                    @if ($crew)
                        <dl class="mt-6 space-y-1 text-sm">
                            @foreach ($crew as $label => $people)
                                <div class="flex gap-3">
                                    <dt class="w-20 shrink-0 text-gray-500">{{ $label }}</dt>
                                    <dd class="text-gray-100">
                                        @foreach ($people as $person)
                                            @php $label = filled($person['name_latin'] ?? null) ? $person['name_latin'].' ('.$person['name'].')' : $person['name']; @endphp
                                            @if ($person['id'] ?? null)
                                                <a href="{{ PersonService::url($person['id'], $person['name_latin'] ?? $person['name']) }}" wire:navigate
                                                   class="underline decoration-gray-600 underline-offset-4 hover:decoration-gray-300">{{ $label }}</a>@if (! $loop->last), @endif
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

                {{-- Review --}}
                <section class="mt-10 border-t border-gray-800 pt-6" aria-labelledby="review-heading">
                    <h2 id="review-heading" class="text-sm font-semibold text-gray-100">Review kamu</h2>

                    @if ($editingReview)
                        <form wire:submit="saveReview" class="mt-3 max-w-prose">
                            <textarea
                                wire:model="form.body"
                                rows="5"
                                placeholder="Apa yang kamu pikirkan soal judul ini?"
                                class="block w-full rounded-md border-gray-700 bg-gray-950 text-gray-100 placeholder:text-gray-500 focus:border-gray-400 focus:ring-gray-400"
                            ></textarea>
                            @error('form.body')
                                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                            @enderror

                            <label class="mt-3 flex items-center gap-2 text-sm text-gray-300">
                                <input type="checkbox" wire:model="form.contains_spoiler"
                                       class="rounded border-gray-600 bg-gray-950 text-perak focus:ring-gray-400">
                                Review ini mengandung spoiler
                            </label>

                            <div class="mt-4 flex gap-2">
                                <x-primary-button>Simpan review</x-primary-button>
                                <button type="button" wire:click="cancelReview" class="px-3 py-2 text-sm text-gray-400 hover:text-gray-100">Batal</button>
                            </div>
                        </form>
                    @elseif ($review)
                        <div class="mt-3 max-w-prose">
                            @if ($review->contains_spoiler)
                                <p class="mb-1 text-xs font-semibold text-red-400">Mengandung spoiler</p>
                            @endif

                            <p class="whitespace-pre-line leading-relaxed text-gray-200">{{ $review->body }}</p>

                            <div class="mt-3 flex gap-4 text-sm">
                                <button type="button" wire:click="editReview" class="text-gray-200 underline underline-offset-4 hover:text-white">Ubah</button>
                                <button type="button" wire:click="deleteReview" class="text-gray-400 underline-offset-4 hover:text-gray-100 hover:underline">Hapus</button>
                            </div>
                        </div>
                    @else
                        <p class="mt-2 text-sm text-gray-400">Kamu belum menulis review untuk judul ini.</p>
                        <x-secondary-button wire:click="editReview" class="mt-3">Tulis review</x-secondary-button>
                    @endif
                </section>

                {{-- Teman yang sudah menonton atau menyimpan judul ini --}}
                <section class="mt-10 border-t border-gray-800 pt-6" aria-labelledby="friends-heading">
                    <h2 id="friends-heading" class="text-sm font-semibold text-gray-100">Teman</h2>

                    @if ($friendEntries->isEmpty())
                        <p class="mt-2 text-sm text-gray-400">Belum ada teman yang menonton atau menyimpan judul ini.</p>
                    @else
                        <ul class="mt-4 space-y-5">
                            @foreach ($friendEntries as $entry)
                                @php $friendReview = $friendReviews->get($entry->user_id); @endphp

                                <li wire:key="friend-entry-{{ $entry->id }}" class="flex gap-3">
                                    <x-avatar :user="$entry->user" size="h-9 w-9 text-xs" />

                                    <div class="min-w-0 flex-1 text-sm">
                                        <p class="text-gray-300">
                                            @if ($entry->user->username)
                                                <a href="{{ route('profile.show', $entry->user) }}" wire:navigate class="font-semibold text-gray-100 hover:underline">{{ $entry->user->name }}</a>
                                            @else
                                                <span class="font-semibold text-gray-100">{{ $entry->user->name }}</span>
                                            @endif

                                            @if ($entry->status === WatchStatus::Watched)
                                                @if ($entry->rating)
                                                    <span class="ms-1 font-semibold text-subtitle">★ {{ $entry->rating }}</span><span class="text-xs text-gray-500">/10</span>
                                                @else
                                                    <span class="text-gray-400">sudah menonton</span>
                                                @endif
                                                @if ($entry->watched_at)
                                                    <span class="ms-1 text-xs text-gray-500">{{ $entry->watched_at->locale('id')->diffForHumans() }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-400">ingin menonton</span>
                                            @endif
                                        </p>

                                        @if ($friendReview)
                                            @if ($friendReview->contains_spoiler)
                                                <details class="mt-1 text-gray-300">
                                                    <summary class="cursor-pointer text-xs text-red-400">Review mengandung spoiler, tampilkan</summary>
                                                    <p class="mt-1 max-w-prose whitespace-pre-line leading-relaxed">{{ $friendReview->body }}</p>
                                                </details>
                                            @else
                                                <p class="mt-1 max-w-prose whitespace-pre-line leading-relaxed text-gray-300">{{ $friendReview->body }}</p>
                                            @endif
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            {{-- Kolom samping: angka dari luar dan tempat menonton --}}
            <aside class="space-y-8 text-sm lg:border-l lg:border-gray-800 lg:pl-8">
                <div>
                    <h2 class="font-semibold text-gray-100">Rating</h2>

                    {{-- Kursi Penuh: persentase penonton yang memberi rating 7 ke atas --}}
                    <div class="mt-3">
                        <p class="flex items-baseline gap-2">
                            <span class="font-condensed text-4xl font-bold leading-none text-gray-100">{{ $community['score'] !== null ? $community['score'].'%' : '–' }}</span>
                            <span class="text-gray-400">Kursi Penuh</span>
                        </p>
                        <x-seat-row :score="$community['score']" class="mt-2 text-base" />

                        <p class="mt-2 text-gray-400">
                            @if ($community['score'] !== null)
                                {{ $community['liked'] }} dari {{ $community['ratings'] }} penonton memberi 7 ke atas.
                                Rata-rata {{ number_format($community['average'], 1, ',', '.') }}/10.
                            @elseif ($community['ratings'] > 0)
                                Belum cukup penonton: baru {{ $community['ratings'] }} dari {{ \App\Support\KursiPenuh::MIN_RATINGS }} rating yang dibutuhkan.
                            @else
                                Belum ada rating di Kursi Penuh. Jadilah yang pertama.
                            @endif
                        </p>
                        @if ($community['watched'] > 0)
                            <p class="text-gray-500">{{ $community['watched'] }} orang sudah menonton</p>
                        @endif
                    </div>

                    {{-- Rating TMDB, IMDb & Rotten Tomatoes; bersama pemain & sutradara diambil setelah halaman tampil --}}
                    <div class="mt-4" @if ($detailsPending) wire:init="loadDetails" @endif>
                        @if ($detailsPending)
                            <div class="space-y-2" aria-label="Memuat rating, pemain, dan sutradara">
                                <div class="h-4 w-3/4 animate-pulse rounded bg-gray-800"></div>
                                <div class="h-4 w-2/3 animate-pulse rounded bg-gray-800"></div>
                                <div class="h-4 w-1/2 animate-pulse rounded bg-gray-800"></div>
                            </div>
                        @else
                            <x-external-ratings :media="$media" />

                            @if ($media->imdbUrl())
                                <a href="{{ $media->imdbUrl() }}" target="_blank" rel="noopener"
                                   class="mt-2 inline-block text-gray-400 underline decoration-gray-600 underline-offset-4 hover:text-gray-100">
                                    Buka di IMDb
                                </a>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Tempat menonton: streaming, gratis, sewa, beli (JustWatch lewat TMDB) --}}
                @if ($detailsPending)
                    <div class="flex gap-1.5">
                        <div class="h-9 w-9 animate-pulse rounded-md bg-gray-800"></div>
                        <div class="h-9 w-9 animate-pulse rounded-md bg-gray-800"></div>
                        <div class="h-9 w-9 animate-pulse rounded-md bg-gray-800"></div>
                    </div>
                @elseif (filled($media->watch_providers['region'] ?? null))
                    @php
                        $typeLabels = ['flatrate' => 'Streaming', 'free' => 'Gratis', 'ads' => 'Gratis (iklan)', 'rent' => 'Sewa', 'buy' => 'Beli'];
                        $groups = collect($typeLabels)
                            ->map(fn ($label, $type) => array_values(array_filter($media->watchProviders(), fn ($provider) => in_array($type, $provider['types'], true))))
                            ->filter();
                    @endphp

                    <div>
                        <h2 class="font-semibold text-gray-100">Tonton di</h2>

                        @if ($groups->isEmpty())
                            <p class="mt-2 text-gray-400">Belum tersedia di platform streaming mana pun di Indonesia.</p>
                        @else
                            <dl class="mt-3 space-y-3">
                                @foreach ($groups as $type => $providers)
                                    <div>
                                        <dt class="text-gray-500">{{ $typeLabels[$type] }}</dt>
                                        <dd class="mt-1 flex flex-wrap gap-1.5">
                                            @foreach ($providers as $provider)
                                                <a href="{{ $media->watch_providers['link'] ?? '#' }}" target="_blank" rel="noopener" title="{{ $provider['name'] }}"
                                                   class="block overflow-hidden rounded-md">
                                                    @if ($provider['logo_url'])
                                                        <img src="{{ $provider['logo_url'] }}" alt="{{ $provider['name'] }}" class="h-9 w-9 object-cover" loading="lazy">
                                                    @else
                                                        <span class="flex h-9 items-center bg-gray-800 px-2 text-xs text-gray-200">{{ $provider['name'] }}</span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        <p class="mt-3 text-xs text-gray-500">Data ketersediaan dari JustWatch</p>
                    </div>
                @endif

                <p class="text-xs text-gray-500">Data judul dari {{ $media->source->label() }}</p>
            </aside>
        </div>

        {{-- Pemain --}}
        @if ($detailsPending)
            <div class="mt-14 grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-6">
                @for ($i = 0; $i < 6; $i++)
                    <div class="animate-pulse space-y-2">
                        <div class="aspect-[2/3] rounded-sm bg-gray-800"></div>
                        <div class="h-3 w-4/5 rounded bg-gray-800"></div>
                    </div>
                @endfor
            </div>
        @elseif ($media->people('cast'))
            <section class="mt-14" aria-labelledby="cast-heading">
                <h2 id="cast-heading" class="font-condensed text-2xl font-bold text-gray-100">Pemain</h2>

                <ul class="mt-4 grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-4 md:grid-cols-6">
                    @foreach ($media->people('cast') as $person)
                        <li class="group relative">
                            @if ($person['id'] ?? null)
                                {{-- Seluruh kartu bisa diklik ke halaman orang --}}
                                <a href="{{ PersonService::url($person['id'], $person['name_latin'] ?? $person['name']) }}" wire:navigate
                                   class="absolute inset-0 z-10 rounded-sm" aria-label="Lihat {{ $person['name_latin'] ?? $person['name'] }}"></a>
                            @endif
                            <div class="aspect-[2/3] overflow-hidden rounded-sm bg-kursi">
                                @if ($person['photo_url'])
                                    <img src="{{ $person['photo_url'] }}" alt="Foto {{ $person['name'] }}" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full items-center justify-center font-condensed text-3xl font-bold text-gray-600" aria-hidden="true">
                                        {{ mb_substr($person['name'], 0, 1) }}
                                    </div>
                                @endif
                            </div>
                            <p class="mt-2 text-sm font-semibold leading-tight text-gray-100 group-hover:underline">{{ $person['name_latin'] ?? null ?: $person['name'] }}</p>
                            @if (filled($person['name_latin'] ?? null))
                                <p class="text-xs leading-tight text-gray-500">{{ $person['name'] }}</p>
                            @endif
                            @if ($person['role'])
                                <p class="mt-0.5 text-xs leading-tight text-gray-400">{{ \App\Support\RoleLabel::translate($person['role']) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
