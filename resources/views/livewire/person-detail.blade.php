@php
    $date = fn (?string $value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale('id')->translatedFormat('j F Y') : null;
@endphp

<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    @if ($failed)
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-200">
            Data orang ini sedang tidak bisa diambil dari TMDB. Coba muat ulang sebentar lagi.
        </div>
    @else
        @php $profile = $data['profile']; @endphp

        <nav aria-label="Breadcrumb">
            <ol class="flex min-w-0 items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
                <li class="shrink-0"><a href="{{ route('search') }}" wire:navigate class="hover:text-gray-800 dark:hover:text-gray-200">Cari</a></li>
                <li class="shrink-0 text-gray-300 dark:text-gray-600" aria-hidden="true">›</li>
                <li class="shrink-0">Orang</li>
                <li class="shrink-0 text-gray-300 dark:text-gray-600" aria-hidden="true">›</li>
                <li class="min-w-0"><span aria-current="page" class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $profile['name_latin'] ?? $profile['name'] }}</span></li>
            </ol>
        </nav>

        <div class="mt-4 grid gap-8 md:grid-cols-[200px_minmax(0,1fr)]">
            <div class="aspect-[2/3] w-full max-w-[200px] overflow-hidden rounded-lg bg-gray-100 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                @if ($profile['photo_url'])
                    <img src="{{ $profile['photo_url'] }}" alt="Foto {{ $profile['name_latin'] ?? $profile['name'] }}" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full items-center justify-center text-5xl font-semibold text-gray-300 dark:text-gray-600" aria-hidden="true">
                        {{ mb_substr($profile['name_latin'] ?? $profile['name'], 0, 1) }}
                    </div>
                @endif
            </div>

            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $profile['name_latin'] ?? $profile['name'] }}</h1>
                @if ($profile['name_latin'])
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $profile['name'] }}</p>
                @endif

                <dl class="mt-3 space-y-1 text-sm">
                    @if ($profile['department'])
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Dikenal lewat</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ \App\Services\Media\PersonService::departmentLabel($profile['department']) }}</dd>
                        </div>
                    @endif
                    @if ($profile['birthday'])
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Lahir</dt>
                            <dd class="text-gray-900 dark:text-gray-100">
                                {{ $date($profile['birthday']) }}@if ($profile['place_of_birth']), {{ $profile['place_of_birth'] }}@endif
                            </dd>
                        </div>
                    @endif
                    @if ($profile['deathday'])
                        <div class="flex gap-2">
                            <dt class="w-24 shrink-0 text-gray-500 dark:text-gray-400">Wafat</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $date($profile['deathday']) }}</dd>
                        </div>
                    @endif
                </dl>

                {{-- Biografi: diterjemahkan ke bahasa Indonesia setelah halaman tampil --}}
                @if ($profile['biography'])
                    <div class="mt-4" @if ($biographyPending) wire:init="translateBiography" @endif>
                        @if ($biographyPending)
                            <div class="space-y-2" aria-label="Menerjemahkan biografi">
                                <div class="h-3 w-full animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="h-3 w-11/12 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                                <div class="h-3 w-3/4 animate-pulse rounded bg-gray-200 dark:bg-gray-700"></div>
                            </div>
                        @elseif ($biographyId && trim($biographyId) !== trim($profile['biography']))
                            <div x-data="{ original: false, open: false }">
                                <p x-show="! original" :class="open ? '' : 'line-clamp-6'" class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $biographyId }}</p>
                                <p x-show="original" x-cloak :class="open ? '' : 'line-clamp-6'" class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $profile['biography'] }}</p>
                                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                    <button type="button" x-on:click="open = ! open" class="underline-offset-2 hover:underline" x-text="open ? 'Ringkas' : 'Baca selengkapnya'">Baca selengkapnya</button> ·
                                    <span x-text="original ? 'Teks asli' : 'Diterjemahkan otomatis'">Diterjemahkan otomatis</span> ·
                                    <button type="button" x-on:click="original = ! original" class="underline-offset-2 hover:underline" x-text="original ? 'Lihat terjemahan' : 'Lihat teks asli'">Lihat teks asli</button>
                                </p>
                            </div>
                        @else
                            <div x-data="{ open: false }">
                                <p :class="open ? '' : 'line-clamp-6'" class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $profile['biography'] }}</p>
                                <button type="button" x-on:click="open = ! open" class="mt-1 text-xs text-gray-400 underline-offset-2 hover:underline dark:text-gray-500" x-text="open ? 'Ringkas' : 'Baca selengkapnya'">Baca selengkapnya</button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Filmografi --}}
        @foreach (['directed' => 'Sebagai Sutradara / Kreator', 'acted' => 'Sebagai Pemain'] as $group => $heading)
            @if ($data[$group])
                <section class="mt-10" aria-labelledby="heading-{{ $group }}">
                    <h2 id="heading-{{ $group }}" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $heading }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ count($data[$group]) }})</span>
                    </h2>

                    <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                        @foreach ($data[$group] as $credit)
                            <a href="{{ $credit['media']->url() }}" wire:navigate wire:key="{{ $group }}-{{ $credit['media']->id }}" class="block">
                                <x-media-card :media="$credit['media']" />
                                @if ($credit['roles'] && $group === 'acted')
                                    <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">sebagai {{ collect($credit['roles'])->map(fn ($role) => \App\Support\RoleLabel::translate($role, inSentence: true))->join(', ') }}</p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @if (! $data['directed'] && ! $data['acted'])
            <p class="mt-10 rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Belum ada judul yang tercatat untuk orang ini.
            </p>
        @endif
    @endif
</div>
