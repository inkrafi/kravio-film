@use('App\Enums\WatchStatus')

<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    <header>
        <h1 class="font-extra-condensed text-4xl font-extrabold leading-none tracking-tight text-white sm:text-5xl">Halo, {{ $user->name }}</h1>
        <p class="mt-2 text-gray-400">
            {{ $watchedCount }} judul sudah ditonton, {{ $watchlistCount }} di watchlist.
        </p>
    </header>

    {{-- Permintaan pertemanan yang menunggu --}}
    @if ($incomingRequests > 0)
        <p class="mt-6 border-l-2 border-perak py-1 pl-3 text-sm text-gray-200">
            {{ $incomingRequests }} permintaan pertemanan menunggu jawabanmu.
            <a href="{{ route('friends') }}" wire:navigate class="font-semibold underline underline-offset-4 hover:text-white">Jawab sekarang</a>
        </p>
    @endif

    {{-- Aktivitas teman, terbaru dulu --}}
    <section class="mt-12" aria-labelledby="activity-heading">
        <h2 id="activity-heading" class="font-condensed text-2xl font-bold text-gray-100">Baru ditonton teman</h2>

        @if ($activity->isEmpty())
            <p class="mt-3 text-gray-400">
                @if ($hasFriends)
                    Teman-temanmu belum mencatat tontonan.
                @else
                    Tambah teman untuk melihat apa yang sedang mereka tonton.
                    <a href="{{ route('friends') }}" wire:navigate class="text-gray-100 underline underline-offset-4 hover:text-white">Cari teman</a>
                @endif
            </p>
        @else
            <ul class="mt-4 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($activity as $entry)
                    @php $review = $reviews->get("{$entry->user_id}-{$entry->media_cache_id}"); @endphp

                    <li wire:key="activity-{{ $entry->id }}">
                        <a href="{{ $entry->media->url() }}" wire:navigate class="group block">
                            <div class="relative aspect-[2/3] overflow-hidden rounded-sm bg-kursi">
                                @if ($entry->media->poster_url)
                                    <img src="{{ $entry->media->poster_url }}" alt="Poster {{ $entry->media->title }}" loading="lazy" class="h-full w-full object-cover">
                                @endif

                                {{-- Review teman sebagai subtitle di atas posternya --}}
                                @if ($review && ! $review->contains_spoiler)
                                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent px-3 pb-3 pt-10">
                                        <p class="subtitle line-clamp-3 text-center text-sm leading-snug">{{ $review->body }}</p>
                                    </div>
                                @endif
                            </div>

                            <p class="mt-2 line-clamp-1 text-sm font-semibold text-gray-100 group-hover:underline">{{ $entry->media->title }}</p>
                        </a>

                        <p class="mt-0.5 text-sm text-gray-400">
                            @if ($entry->user->username)
                                <a href="{{ route('profile.show', $entry->user) }}" wire:navigate class="text-gray-200 hover:underline">{{ $entry->user->name }}</a>
                            @else
                                <span class="text-gray-200">{{ $entry->user->name }}</span>
                            @endif
                            @if ($entry->rating)
                                <span class="ms-1 font-semibold text-subtitle">★ {{ $entry->rating }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">{{ $entry->watched_at->locale('id')->diffForHumans() }}</p>

                        @if ($review?->contains_spoiler)
                            <details class="mt-1 text-sm text-gray-300">
                                <summary class="cursor-pointer text-xs text-red-400">Review mengandung spoiler, tampilkan</summary>
                                <p class="mt-1 line-clamp-6 whitespace-pre-line">{{ $review->body }}</p>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Watchlist --}}
    <section class="mt-16" aria-labelledby="watchlist-heading">
        <div class="flex items-baseline justify-between gap-3">
            <h2 id="watchlist-heading" class="font-condensed text-2xl font-bold text-gray-100">Watchlist kamu</h2>
            @if ($watchlistCount > $watchlist->count() && $user->username)
                <a href="{{ route('profile.show', ['user' => $user, 'daftar' => WatchStatus::Watchlist->value]) }}" wire:navigate
                   class="text-sm text-gray-300 underline underline-offset-4 hover:text-white">Lihat semua {{ $watchlistCount }}</a>
            @endif
        </div>

        @if ($watchlist->isEmpty())
            <p class="mt-3 text-gray-400">
                Watchlist masih kosong. Simpan judul yang ingin kamu tonton dari halaman detailnya, atau
                <a href="{{ route('search') }}" wire:navigate class="text-gray-100 underline underline-offset-4 hover:text-white">cari judul</a>.
            </p>
        @else
            <div class="mt-4 grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-6">
                @foreach ($watchlist as $entry)
                    <a href="{{ $entry->media->url() }}" wire:navigate wire:key="watchlist-{{ $entry->id }}" class="block">
                        <x-media-card :media="$entry->media" />
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Rekomendasi dari insight terakhir --}}
    <section class="mt-16" aria-labelledby="recs-heading">
        <div class="flex items-baseline justify-between gap-3">
            <h2 id="recs-heading" class="font-condensed text-2xl font-bold text-gray-100">Rekomendasi untukmu</h2>
            <a href="{{ route('insight') }}" wire:navigate class="text-sm text-gray-300 underline underline-offset-4 hover:text-white">Buka insight</a>
        </div>

        @if ($recommendations->isEmpty())
            <p class="mt-3 text-gray-400">
                Rekomendasi muncul setelah insight mingguanmu dibuat dari riwayat tontonan.
            </p>
        @else
            <div class="mt-4 grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-6">
                @foreach ($recommendations as $item)
                    <a href="{{ $item->url() }}" wire:navigate wire:key="rec-{{ $item->id }}" class="block">
                        <x-media-card :media="$item" />
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
