@use('App\Services\Insights\InsightService')

{{-- Selama insight dibuat di queue, halaman memeriksa hasilnya tiap 5 detik. --}}
<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8" @if ($pending) wire:poll.5s @endif>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Insight Mingguan</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($insight)
                    Terakhir update: {{ $insight->generated_at->locale('id')->translatedFormat('j F Y, H:i') }}
                    · diperbarui otomatis tiap Senin
                @else
                    Analisis kebiasaan nontonmu dan rekomendasi, diperbarui otomatis tiap Senin.
                @endif
            </p>
        </div>

        @if ($configured && $enoughData)
            <div class="shrink-0 text-right">
                @if ($pending)
                    <span class="inline-flex items-center gap-2 rounded-lg bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        Sedang dianalisis…
                    </span>
                @elseif ($canRefresh)
                    <button type="button" wire:click="generate"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        {{ $insight ? 'Perbarui sekarang' : 'Buat insight sekarang' }}
                    </button>
                @else
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Bisa diperbarui manual lagi {{ $insight->generated_at->addHours(InsightService::MANUAL_COOLDOWN_HOURS)->locale('id')->diffForHumans() }}.
                    </p>
                @endif
            </div>
        @endif
    </header>

    @if ($failed && ! $pending)
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-200">
            @if ($failed === \App\Jobs\GenerateUserInsight::FAILED_QUOTA)
                Insight gagal dibuat: kuota harian AI (paket gratis Gemini) sudah habis. Kuota direset setiap hari
                sekitar pukul 14.00–15.00 WIB — coba lagi setelahnya.
            @else
                Insight gagal dibuat — layanan AI mungkin sedang sibuk. Coba lagi beberapa saat lagi.
            @endif
        </div>
    @endif

    @if (! $configured)
        <p class="mt-6 rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Insight AI belum aktif: <code>GEMINI_API_KEY</code> belum diisi.
        </p>
    @elseif (! $insight && ! $enoughData)
        <p class="mt-6 rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Tandai minimal {{ $minWatched }} judul sebagai sudah ditonton dulu, supaya ada pola yang bisa dianalisis.
        </p>
    @elseif (! $insight)
        @if ($pending)
            <div class="mt-6 space-y-3 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700" aria-label="Membuat insight">
                <div class="h-5 w-2/3 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-3 w-full animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-3 w-11/12 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-3 w-3/4 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
        @else
            <p class="mt-6 rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Belum ada insight. Tekan "Buat insight sekarang" atau tunggu pembaruan otomatis hari Senin.
            </p>
        @endif
    @else
        {{-- Ringkasan --}}
        <section class="mt-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700" aria-labelledby="insight-headline">
            <h2 id="insight-headline" class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $content['headline'] }}</h2>

            <div class="mt-3 space-y-3 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                @foreach (preg_split('/\n\s*\n/', $content['summary']) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>

            @if ($content['highlights'])
                <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach ($content['highlights'] as $highlight)
                        <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-900/40">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-indigo-600 dark:text-indigo-400">{{ $highlight['label'] }}</dt>
                            <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $highlight['text'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>

        {{-- Rekomendasi personal --}}
        @if ($content['recommendations'])
            <section class="mt-10" aria-labelledby="recs-heading">
                <h2 id="recs-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Rekomendasi untukmu</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Dipilih dari judul yang mirip dengan tontonan favoritmu dan genre yang paling sering kamu tonton.</p>

                <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($content['recommendations'] as $recommendation)
                        @if ($item = $media->get($recommendation['media_id']))
                            <a href="{{ $item->url() }}" wire:navigate wire:key="rec-{{ $item->id }}" class="block">
                                <x-media-card :media="$item" />
                                @if ($recommendation['reason'])
                                    <p class="mt-1.5 text-xs leading-snug text-gray-600 dark:text-gray-400">{{ $recommendation['reason'] }}</p>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Kesamaan dengan teman --}}
        <section class="mt-10" aria-labelledby="friends-heading">
            <h2 id="friends-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Kesamaan selera dengan teman</h2>

            @if (! $content['friends'])
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Belum ada teman dengan riwayat yang cukup untuk dibandingkan. <a href="{{ route('friends') }}" wire:navigate class="text-indigo-600 hover:underline dark:text-indigo-400">Cari teman</a>
                </p>
            @else
                <ul class="mt-3 space-y-4">
                    @foreach ($content['friends'] as $match)
                        @if ($friend = $friendUsers->get($match['user_id']))
                            <li wire:key="friend-{{ $friend->id }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('profile.show', $friend) }}" wire:navigate><x-avatar :user="$friend" /></a>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline justify-between gap-3">
                                            <a href="{{ route('profile.show', $friend) }}" wire:navigate class="truncate text-sm font-medium text-gray-900 hover:underline dark:text-gray-100">{{ $friend->name }}</a>
                                            <span class="shrink-0 text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ round($match['similarity'] * 100) }}% mirip</span>
                                        </div>
                                        <div class="mt-1 h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-700" aria-hidden="true">
                                            <div class="h-1.5 rounded-full bg-indigo-500 dark:bg-indigo-400" style="width: {{ max(2, round($match['similarity'] * 100)) }}%"></div>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @if ($match['shared_genres'])
                                                Sama-sama suka {{ implode(', ', $match['shared_genres']) }}.
                                            @endif
                                            {{ $match['shared_titles'] }} judul ditonton bersama.
                                        </p>
                                    </div>
                                </div>

                                @if ($match['picks'])
                                    <p class="mt-3 text-xs font-medium text-gray-500 dark:text-gray-400">Favorit {{ $friend->name }} yang belum kamu tonton</p>
                                    <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        @foreach ($match['picks'] as $pick)
                                            @if ($item = $media->get($pick['media_id']))
                                                <a href="{{ $item->url() }}" wire:navigate class="relative block">
                                                    <x-media-card :media="$item" />
                                                    <span class="absolute right-2 top-2 rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-white shadow"
                                                          title="Rating {{ $friend->name }}">{{ $pick['rating'] }}</span>
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
