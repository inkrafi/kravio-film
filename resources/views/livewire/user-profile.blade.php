@use('App\Enums\FriendshipState')
@use('App\Enums\WatchStatus')

<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    {{-- Header profil --}}
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
            <x-avatar :user="$user" size="h-20 w-20 text-2xl" />

            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $user->name }}</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">&#64;{{ $user->username }}</p>

                @if ($user->bio)
                    <p class="mt-2 max-w-prose whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $user->bio }}</p>
                @endif

                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                    {{ $friendCount }} teman
                    @if ($canViewLibrary)
                        · {{ $counts[WatchStatus::Watched->value] }} ditonton
                        · {{ $counts[WatchStatus::Watchlist->value] }} di watchlist
                    @endif
                </p>
            </div>
        </div>

        {{-- Tombol status pertemanan --}}
        <div class="shrink-0">
            @switch ($state)
                @case (FriendshipState::Self)
                    <a href="{{ route('profile') }}" wire:navigate
                       class="inline-block rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        Edit profil
                    </a>
                    @break

                @case (FriendshipState::None)
                    <button type="button" wire:click="sendFriendRequest"
                            class="rounded-lg bg-perak px-4 py-2 text-sm font-medium text-layar hover:bg-white">
                        Tambah Teman
                    </button>
                    @break

                @case (FriendshipState::PendingOutgoing)
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg bg-amber-100 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                            Menunggu konfirmasi
                        </span>
                        <button type="button" wire:click="cancelFriendRequest"
                                class="text-sm text-gray-500 hover:underline dark:text-gray-400">
                            Batalkan
                        </button>
                    </div>
                    @break

                @case (FriendshipState::PendingIncoming)
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="acceptFriendRequest"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            Terima
                        </button>
                        <button type="button" wire:click="rejectFriendRequest"
                                class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                            Tolak
                        </button>
                    </div>
                    @break

                @case (FriendshipState::Friends)
                    <div class="flex items-center gap-2">
                        <span class="rounded-lg bg-emerald-100 px-3 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                            ✓ Berteman
                        </span>
                        <button type="button" wire:click="removeFriend"
                                class="text-sm text-gray-500 hover:underline dark:text-gray-400">
                            Putuskan
                        </button>
                    </div>
                    @break
            @endswitch

            @error('friendship')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </header>

    {{-- Favorit; pemiliknya bisa membagikannya sebagai gambar atau tautan --}}
    <section class="mt-10" x-data="{ sharing: false }">
        <div class="flex items-baseline justify-between gap-3">
            <h2 class="text-sm font-semibold text-gray-400">Favorit</h2>

            @if ($this->isOwnProfile && $favorites->isNotEmpty() && $user->username)
                <button type="button" x-on:click="sharing = ! sharing" :aria-expanded="sharing"
                        class="text-sm font-semibold text-gray-200 underline underline-offset-4 hover:text-white">
                    <span x-text="sharing ? 'Tutup' : 'Bagikan'">Bagikan</span>
                </button>
            @endif
        </div>

        @if ($favorites->isEmpty())
            <p class="mt-2 text-sm text-gray-400">
                {{ $this->isOwnProfile
                    ? 'Belum ada favorit. Buka halaman judul mana pun lalu tekan "Favorit".'
                    : 'Belum memilih judul favorit.' }}
            </p>
        @else
            @if ($shareVersion)
                @php
                    $shareLink = route('favorites.share', $user);
                    $imageUrl = fn (string $format, bool $download = false) => route('favorites.image', ['user' => $user, 'format' => $format]).'?v='.$shareVersion.($download ? '&unduh=1' : '');
                @endphp

                <div x-show="sharing" x-cloak x-transition.opacity class="mt-4 grid gap-6 rounded-md bg-kursi p-4 sm:grid-cols-[14rem_minmax(0,1fr)]">
                    <template x-if="sharing">
                        <img src="{{ $imageUrl('kotak') }}" alt="Pratinjau gambar favoritmu" class="aspect-square w-full rounded-sm bg-layar">
                    </template>

                    <div x-data="{ copied: false, canShare: !! navigator.share }">
                        <p class="text-sm text-gray-300">Gambar berisi favoritmu, dengan bio sebagai baris subtitle. Unduh lalu unggah ke Instagram, WhatsApp, atau X.</p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <a href="{{ $imageUrl('kotak', true) }}" class="rounded-md bg-perak px-4 py-2 text-sm font-semibold text-layar hover:bg-white">Unduh kotak</a>
                            <a href="{{ $imageUrl('story', true) }}" class="rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-gray-100 hover:bg-gray-600">Unduh story</a>
                        </div>

                        <x-input-label for="favorite-share-link" value="Tautan" class="mt-4" />
                        <div class="mt-1 flex gap-2">
                            <input id="favorite-share-link" type="text" readonly value="{{ $shareLink }}" x-on:focus="$el.select()"
                                   class="min-w-0 flex-1 rounded-md border-gray-700 bg-layar text-sm text-gray-300 focus:border-gray-400 focus:ring-gray-400">
                            <button type="button"
                                    x-on:click="navigator.clipboard.writeText(@js($shareLink)); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="shrink-0 rounded-md bg-gray-700 px-3 text-sm font-semibold text-gray-100 hover:bg-gray-600"
                                    x-text="copied ? 'Tersalin' : 'Salin'">Salin</button>
                        </div>

                        <button type="button" x-show="canShare" x-cloak
                                x-on:click="navigator.share({ title: @js('Favorit '.$user->name), url: @js($shareLink) })"
                                class="mt-2 rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-gray-100 hover:bg-gray-600">
                            Bagikan lewat aplikasi lain
                        </button>

                        <p class="mt-3 text-xs text-gray-500">Siapa pun yang punya tautan ini bisa melihat favoritmu tanpa login. Watched dan diary-mu tetap privat.</p>
                    </div>
                </div>
            @endif

            <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($favorites as $favorite)
                    <a href="{{ $favorite->media->url() }}" wire:navigate wire:key="fav-{{ $favorite->id }}">
                        <x-media-card :media="$favorite->media" />
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Watched / watchlist / diary dikunci kalau belum berteman; tab List selalu ada
         karena visibilitasnya diatur per list. --}}
    <section class="mt-10">
        @if (! $canViewLibrary)
            <div class="rounded-lg border border-dashed border-gray-300 px-6 py-8 text-center dark:border-gray-700">
                <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Riwayat tontonan disembunyikan</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Watched, watchlist, diary, dan statistik {{ $user->name }} hanya terlihat oleh teman.
                    List yang ia bagikan tetap bisa dilihat di tab List.
                </p>
            </div>
        @else
            {{-- Statistik --}}
            <div aria-labelledby="stats-heading">
                <h2 id="stats-heading" class="text-sm font-semibold text-gray-500 dark:text-gray-400">Statistik</h2>

                <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Rata-rata rating</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">
                            @if ($stats['average_rating'] !== null)
                                {{ number_format($stats['average_rating'], 1, ',', '.') }}<span class="text-sm font-medium text-gray-400 dark:text-gray-500">/10</span>
                            @else
                                –
                            @endif
                        </dd>
                        <dd class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $stats['rated'] ? 'dari '.$stats['rated'].' judul yang dirating' : 'Belum ada rating' }}
                        </dd>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Ditonton tahun {{ now()->year }}</dt>
                        <dd class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $stats['this_year'] }}</dd>
                        <dd class="text-xs text-gray-500 dark:text-gray-400">judul</dd>
                    </div>
                </dl>

                {{-- Sudah ditonton: satu bar bagian-dari-total (Film/Series/Anime) + angka;
                     palet sky/emerald/fuchsia sudah divalidasi untuk buta warna & mode gelap. --}}
                @php
                    $types = [
                        ['label' => 'Film', 'count' => $stats['films'], 'color' => 'bg-sky-500 dark:bg-sky-600'],
                        ['label' => 'Series', 'count' => $stats['series'], 'color' => 'bg-emerald-500 dark:bg-emerald-600'],
                        ['label' => 'Anime', 'count' => $stats['anime'], 'color' => 'bg-fuchsia-500 dark:bg-fuchsia-500'],
                    ];
                    $percent = fn (int $count) => $stats['total'] ? round($count / $stats['total'] * 100) : 0;
                @endphp

                <div class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <div class="flex items-baseline justify-between gap-3">
                            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Sudah ditonton</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <span class="text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $stats['total'] }}</span> judul
                            </p>
                        </div>

                        @if ($stats['total'])
                            {{-- Segmen dipisah celah 2px; lebar = porsi dari total --}}
                            <div class="mt-3 flex h-2.5 w-full gap-0.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700"
                                 role="img" aria-label="{{ collect($types)->map(fn ($type) => $type['label'].' '.$type['count'])->join(', ') }} dari {{ $stats['total'] }} judul">
                                @foreach ($types as $type)
                                    @if ($type['count'])
                                        <div class="h-full {{ $type['color'] }}" style="width: {{ max(2, $percent($type['count'])) }}%"
                                             title="{{ $type['label'] }}: {{ $type['count'] }} judul ({{ $percent($type['count']) }}%)"></div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="mt-3 h-2.5 w-full rounded-full bg-gray-100 dark:bg-gray-700" aria-hidden="true"></div>
                        @endif

                        {{-- Legenda dengan angka langsung, jadi warna bukan satu-satunya penanda --}}
                        <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                            @foreach ($types as $type)
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $type['color'] }}" aria-hidden="true"></span>
                                    <dt class="text-gray-600 dark:text-gray-300">{{ $type['label'] }}</dt>
                                    <dd class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $type['count'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Genre teratas</h3>
                        @if ($stats['genres'])
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach (array_slice($stats['genres'], 0, 3) as $genre)
                                    <a href="{{ route('genre.show', ['slug' => \App\Support\GenreNormalizer::slug($genre['name'])]) }}" wire:navigate
                                       title="{{ $genre['count'] }} judul"
                                       class="inline-flex items-center gap-1.5 rounded-full bg-gray-800 px-3 py-1 text-sm font-medium text-perak ring-1 ring-inset ring-gray-400 hover:bg-gray-800 dark:bg-gray-700/30 dark:text-perak dark:ring-gray-400 dark:hover:bg-gray-700/50">
                                        {{ $genre['name'] }}
                                        <span class="text-xs font-normal tabular-nums text-perak dark:text-perak">{{ $genre['count'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Muncul setelah ada judul yang ditandai sudah ditonton.</p>
                        @endif
                    </div>
                </div>
            </div>

        @endif

        {{-- Tab watched / watchlist / diary / list --}}
            <div @class(['flex flex-wrap gap-2', 'mt-10' => $canViewLibrary, 'mt-6' => ! $canViewLibrary]) role="tablist" aria-label="Daftar tontonan">
                @foreach ($tabs as $value => $label)
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $tab === $value ? 'true' : 'false' }}"
                        wire:click="selectTab('{{ $value }}')"
                        @class([
                            'rounded-full px-4 py-1.5 text-sm font-medium transition',
                            'bg-perak text-layar' => $tab === $value,
                            'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $tab !== $value,
                        ])
                    >
                        {{ $label }}@isset($counts[$value]) ({{ $counts[$value] }})@endisset
                    </button>
                @endforeach
            </div>

            @if ($isLists)
                {{-- List: hanya yang boleh dilihat pengunjung --}}
                @if ($this->isOwnProfile)
                    <div class="mt-4 flex justify-end">
                        <a href="{{ route('lists.create') }}" wire:navigate
                           class="rounded-lg bg-perak px-4 py-2 text-sm font-medium text-layar hover:bg-white">＋ Buat list</a>
                    </div>
                @endif

                @if ($entries->isEmpty())
                    <p class="mt-4 rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        {{ $this->isOwnProfile ? 'Belum ada list. Kumpulkan judul favoritmu dalam list, mis. "Top 10 anime".' : 'Belum ada list yang bisa kamu lihat.' }}
                    </p>
                @else
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($entries as $list)
                            <x-list-card :list="$list" :show-owner="false" wire:key="profile-list-{{ $list->id }}" />
                        @endforeach
                    </div>

                    <div class="mt-6">{{ $entries->links() }}</div>
                @endif
            @elseif ($entries->isEmpty())
                <p class="mt-6 rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    {{ $isDiary ? 'Diary masih kosong. Judul yang ditandai sudah ditonton akan tercatat di sini.' : 'Belum ada judul di daftar ini.' }}
                </p>
            @elseif ($isDiary)
                {{-- Diary: kronologis per bulan, terbaru di atas --}}
                <div class="mt-4 space-y-8">
                    @foreach ($entries->getCollection()->groupBy(fn ($entry) => $entry->watched_at->format('Y-m')) as $month => $monthEntries)
                        <section wire:key="diary-{{ $month }}" aria-label="{{ $monthEntries->first()->watched_at->locale('id')->translatedFormat('F Y') }}">
                            <h3 class="border-b border-gray-200 pb-1 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                {{ $monthEntries->first()->watched_at->locale('id')->translatedFormat('F Y') }}
                            </h3>

                            <ol class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($monthEntries as $entry)
                                    <li wire:key="diary-entry-{{ $entry->id }}" class="flex items-center gap-4 py-3">
                                        <time datetime="{{ $entry->watched_at->toDateString() }}" class="w-10 shrink-0 text-center">
                                            <span class="block text-lg font-bold leading-none tabular-nums text-gray-900 dark:text-gray-100">{{ $entry->watched_at->format('j') }}</span>
                                            <span class="block text-[11px] uppercase text-gray-400 dark:text-gray-500">{{ $entry->watched_at->locale('id')->translatedFormat('D') }}</span>
                                        </time>

                                        <a href="{{ $entry->media->url() }}" wire:navigate class="h-14 w-10 shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                                            @if ($entry->media->poster_url)
                                                <img src="{{ $entry->media->poster_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                            @endif
                                        </a>

                                        <div class="min-w-0 flex-1">
                                            <a href="{{ $entry->media->url() }}" wire:navigate
                                               class="block truncate text-sm font-medium text-gray-900 hover:underline dark:text-gray-100">
                                                {{ $entry->media->title }}
                                            </a>
                                            @if ($entry->media->title_latin)
                                                <p class="truncate text-xs text-gray-600 dark:text-gray-300">{{ $entry->media->title_latin }}</p>
                                            @endif
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $entry->media->media_type->label() }}@if ($entry->media->year) · {{ $entry->media->year }}@endif
                                            </p>
                                        </div>

                                        @if (in_array($entry->media_cache_id, $reviewedMediaIds, strict: true))
                                            <span class="shrink-0 text-gray-400 dark:text-gray-500" title="Ada review">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                                                </svg>
                                                <span class="sr-only">Ada review</span>
                                            </span>
                                        @endif

                                        <span class="w-14 shrink-0 text-right text-sm tabular-nums">
                                            @if ($entry->rating)
                                                <span class="font-semibold text-amber-600 dark:text-amber-400">★ {{ $entry->rating }}</span><span class="text-xs text-gray-400">/10</span>
                                            @else
                                                <span class="text-xs text-gray-400 dark:text-gray-500">–</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $entries->links() }}
                </div>
            @else
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($entries as $entry)
                        <a href="{{ $entry->media->url() }}" wire:navigate wire:key="entry-{{ $entry->id }}" class="relative block">
                            <x-media-card :media="$entry->media" />

                            @if ($entry->rating)
                                <span class="absolute right-2 top-2 rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-white shadow">
                                    {{ $entry->rating }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $entries->links() }}
                </div>
            @endif
    </section>

    {{-- Komentar: komponen terpisah, polling sendiri tanpa me-render ulang profil --}}
    <livewire:profile-comments :user="$user" :key="'comments-'.$user->id" />
</div>
