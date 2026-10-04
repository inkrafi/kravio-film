@use('App\Enums\WatchStatus')

<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Halo, {{ $user->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $watchedCount }} judul sudah ditonton · {{ $watchlistCount }} di watchlist
            </p>
        </div>

        <a href="{{ route('search') }}" wire:navigate
           class="shrink-0 rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-indigo-700">
            Cari film, series, anime
        </a>
    </header>

    {{-- Permintaan pertemanan yang menunggu --}}
    @if ($incomingRequests > 0)
        <a href="{{ route('friends') }}" wire:navigate
           class="mt-6 flex items-center justify-between gap-3 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-800 ring-1 ring-indigo-100 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-200 dark:ring-indigo-900 dark:hover:bg-indigo-900/50">
            <span>{{ $incomingRequests }} permintaan pertemanan menunggu jawabanmu</span>
            <span aria-hidden="true">→</span>
        </a>
    @endif

    {{-- Aktivitas teman --}}
    <section class="mt-8" aria-labelledby="activity-heading">
        <h2 id="activity-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Baru ditonton teman</h2>

        @if ($activity->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                @if ($hasFriends)
                    Teman-temanmu belum mencatat tontonan.
                @else
                    Tambah teman untuk melihat apa yang sedang mereka tonton.
                    <a href="{{ route('friends') }}" wire:navigate class="text-indigo-600 hover:underline dark:text-indigo-400">Cari teman</a>
                @endif
            </p>
        @else
            <ul class="mt-3 divide-y divide-gray-100 rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:divide-gray-700 dark:bg-gray-800 dark:ring-gray-700">
                @foreach ($activity as $entry)
                    @php $review = $reviews->get("{$entry->user_id}-{$entry->media_cache_id}"); @endphp

                    <li wire:key="activity-{{ $entry->id }}" class="flex gap-3 p-3">
                        <a href="{{ $entry->media->url() }}" wire:navigate class="block w-12 shrink-0">
                            <div class="aspect-[2/3] overflow-hidden rounded bg-gray-100 dark:bg-gray-900">
                                @if ($entry->media->poster_url)
                                    <img src="{{ $entry->media->poster_url }}" alt="Poster {{ $entry->media->title }}" loading="lazy" class="h-full w-full object-cover">
                                @endif
                            </div>
                        </a>

                        <div class="min-w-0 flex-1 text-sm">
                            <p class="text-gray-700 dark:text-gray-300">
                                @if ($entry->user->username)
                                    <a href="{{ route('profile.show', $entry->user) }}" wire:navigate class="font-semibold text-gray-900 hover:underline dark:text-gray-100">{{ $entry->user->name }}</a>
                                @else
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $entry->user->name }}</span>
                                @endif
                                menonton
                                <a href="{{ $entry->media->url() }}" wire:navigate class="font-semibold text-gray-900 hover:text-indigo-600 hover:underline dark:text-gray-100 dark:hover:text-indigo-400">{{ $entry->media->title }}</a>
                                @if ($entry->media->year)
                                    <span class="text-gray-400 dark:text-gray-500">({{ $entry->media->year }})</span>
                                @endif
                            </p>

                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                @if ($entry->rating)
                                    <span class="font-semibold text-amber-600 dark:text-amber-400">★ {{ $entry->rating }}</span><span class="text-gray-400">/10</span> ·
                                @endif
                                {{ $entry->watched_at->locale('id')->diffForHumans() }}
                            </p>

                            @if ($review)
                                @if ($review->contains_spoiler)
                                    <details class="mt-1 text-gray-600 dark:text-gray-400">
                                        <summary class="cursor-pointer text-xs text-rose-600 dark:text-rose-400">Review mengandung spoiler, tampilkan</summary>
                                        <p class="mt-1 line-clamp-3 whitespace-pre-line">{{ $review->body }}</p>
                                    </details>
                                @else
                                    <p class="mt-1 line-clamp-3 whitespace-pre-line text-gray-600 dark:text-gray-400">{{ $review->body }}</p>
                                @endif
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Watchlist --}}
    <section class="mt-10" aria-labelledby="watchlist-heading">
        <div class="flex items-baseline justify-between gap-3">
            <h2 id="watchlist-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Watchlist kamu</h2>
            @if ($watchlistCount > $watchlist->count() && $user->username)
                <a href="{{ route('profile.show', ['user' => $user, 'daftar' => WatchStatus::Watchlist->value]) }}" wire:navigate
                   class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">Lihat semua ({{ $watchlistCount }})</a>
            @endif
        </div>

        @if ($watchlist->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Watchlist masih kosong. Simpan judul yang ingin kamu tonton dari halaman detailnya.
            </p>
        @else
            <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-6">
                @foreach ($watchlist as $entry)
                    <a href="{{ $entry->media->url() }}" wire:navigate wire:key="watchlist-{{ $entry->id }}" class="block">
                        <x-media-card :media="$entry->media" />
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Rekomendasi dari insight terakhir --}}
    <section class="mt-10" aria-labelledby="recs-heading">
        <div class="flex items-baseline justify-between gap-3">
            <h2 id="recs-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Rekomendasi untukmu</h2>
            <a href="{{ route('insight') }}" wire:navigate class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">Buka insight</a>
        </div>

        @if ($recommendations->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Rekomendasi muncul setelah insight mingguanmu dibuat dari riwayat tontonan.
            </p>
        @else
            <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-6">
                @foreach ($recommendations as $item)
                    <a href="{{ $item->url() }}" wire:navigate wire:key="rec-{{ $item->id }}" class="block">
                        <x-media-card :media="$item" />
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
