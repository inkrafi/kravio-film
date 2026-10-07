@php
    $appName = config('app.name');

    // Denah contoh: 7 baris x 12 kursi, terisi 87% dalam urutan acak yang tetap.
    $rows = range('A', 'G');
    $seatsPerRow = 12;
    $sampleScore = 87;
    $total = count($rows) * $seatsPerRow;
    $order = range(0, $total - 1);
    mt_srand(7);
    shuffle($order);
    mt_srand();
    $fillOrder = array_flip(array_slice($order, 0, (int) round($total * $sampleScore / 100)));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $appName }}: catat, nilai, dan lihat yang ditonton temanmu</title>
        <meta name="description" content="Catat film, series, dan anime yang kamu tonton, beri rating 1 sampai 10, dan lihat skor Kursi Penuh: persentase penonton yang menyukai sebuah judul.">
        <meta property="og:title" content="{{ $appName }}">
        <meta property="og:description" content="Film bagus selalu kursi penuh. Catat, nilai, dan lihat yang ditonton temanmu.">
        <meta property="og:type" content="website">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-layar font-sans text-gray-200 antialiased">
        <header class="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="text-perak"><x-application-logo class="text-[1.75rem]" /></a>

            <nav class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-md bg-perak px-4 py-2 font-semibold text-layar hover:bg-white">Buka beranda</a>
                @else
                    <a href="{{ route('login') }}" class="text-gray-300 hover:text-white">Masuk</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-perak px-4 py-2 font-semibold text-layar hover:bg-white">Daftar gratis</a>
                @endauth
            </nav>
        </header>

        <main>
            {{-- Hero: denah kursi bioskop yang terisi sampai skor contoh --}}
            <section class="mx-auto grid max-w-6xl items-center gap-12 px-4 pb-20 pt-10 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:px-8 lg:pt-16">
                <div>
                    <h1 class="font-extra-condensed text-6xl font-extrabold leading-[0.9] tracking-tight text-white sm:text-7xl lg:text-8xl">Film bagus selalu kursi penuh.</h1>
                    <p class="mt-6 max-w-md text-lg leading-relaxed text-gray-300">
                        Catat yang kamu tonton, beri rating 1 sampai 10, dan lihat berapa persen penonton yang menyukai sebuah film.
                        Persen itulah skor Kursi Penuh.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-md bg-perak px-6 py-3 font-semibold text-layar hover:bg-white">Buka beranda</a>
                        @else
                            <a href="{{ route('register') }}" class="rounded-md bg-perak px-6 py-3 font-semibold text-layar hover:bg-white">Daftar gratis</a>
                            <p class="text-sm text-gray-400">
                                Sudah punya akun? <a href="{{ route('login') }}" class="text-gray-100 underline underline-offset-4 hover:text-white">Masuk</a>
                            </p>
                        @endauth
                    </div>
                </div>

                <figure class="rounded-md bg-kursi px-4 pb-6 pt-5 sm:px-8">
                    {{-- Layar di depan, seperti denah pemilihan kursi bioskop --}}
                    <div aria-hidden="true">
                        <svg viewBox="0 0 400 24" class="w-full text-gray-500" fill="none">
                            <path d="M8 20 Q200 -4 392 20" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        <p class="-mt-1 text-center text-xs text-gray-500">Layar</p>
                    </div>

                    <div class="mt-6 space-y-1.5" role="img" aria-label="Contoh denah: {{ count($fillOrder) }} dari {{ $total }} kursi terisi, Kursi Penuh {{ $sampleScore }}%">
                        @foreach ($rows as $rowIndex => $row)
                            <div class="flex items-center gap-2" aria-hidden="true">
                                <span class="w-3 shrink-0 text-[10px] text-gray-600">{{ $row }}</span>
                                <div class="grid flex-1 grid-cols-[repeat(6,minmax(0,1fr))_0.6rem_repeat(6,minmax(0,1fr))] gap-1 sm:gap-1.5">
                                    @for ($seat = 0; $seat < $seatsPerRow; $seat++)
                                        @php $index = $rowIndex * $seatsPerRow + $seat; @endphp
                                        @if ($seat === 6)
                                            <span></span>
                                        @endif
                                        <svg viewBox="0 0 20 22"
                                             @class([
                                                 'w-full fill-current',
                                                 'seat-fill text-subtitle' => isset($fillOrder[$index]),
                                                 'text-gray-700' => ! isset($fillOrder[$index]),
                                             ])
                                             @isset($fillOrder[$index]) style="animation-delay: {{ 200 + $fillOrder[$index] * 16 }}ms" @endisset>
                                            <path d="M5 1h10a3 3 0 0 1 3 3v8H2V4a3 3 0 0 1 3-3Z" />
                                            <path d="M0 13h20v4H0z" />
                                            <path d="M2 17h3v5H2zM15 17h3v5h-3z" />
                                        </svg>
                                    @endfor
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <figcaption class="mt-6 flex items-baseline gap-3">
                        <span class="font-condensed text-5xl font-bold leading-none text-white">{{ $sampleScore }}%</span>
                        <span class="text-sm text-gray-400">Contoh skor: {{ $sampleScore }} dari 100 penonton memberi rating 7 ke atas.</span>
                    </figcaption>
                </figure>
            </section>

            {{-- Cara skor dihitung: memang berurutan, jadi diberi nomor --}}
            <section class="border-t border-gray-800">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
                    <h2 class="font-condensed text-4xl font-bold text-white">Cara skor dihitung</h2>

                    <ol class="mt-10 grid gap-10 md:grid-cols-3">
                        <li>
                            <p class="font-condensed text-2xl font-bold text-gray-500">1</p>
                            <h3 class="mt-2 text-lg font-semibold text-gray-100">Penonton memberi rating</h3>
                            <p class="mt-2 max-w-xs leading-relaxed text-gray-400">Setiap orang menilai dari 1 sampai 10 setelah selesai menonton.</p>
                        </li>
                        <li>
                            <p class="font-condensed text-2xl font-bold text-gray-500">2</p>
                            <h3 class="mt-2 text-lg font-semibold text-gray-100">Tujuh ke atas mengisi kursi</h3>
                            <p class="mt-2 max-w-xs leading-relaxed text-gray-400">Rating 7 atau lebih berarti penonton itu suka, dan satu kursi terisi.</p>
                        </li>
                        <li>
                            <p class="font-condensed text-2xl font-bold text-gray-500">3</p>
                            <h3 class="mt-2 text-lg font-semibold text-gray-100">Skornya persen kursi terisi</h3>
                            <p class="mt-2 max-w-xs leading-relaxed text-gray-400">Skor baru tampil setelah ada minimal {{ \App\Support\KursiPenuh::MIN_RATINGS }} rating, supaya satu-dua orang tidak menentukan.</p>
                            <x-seat-row :score="$sampleScore" class="mt-4 text-xl" />
                        </li>
                    </ol>
                </div>
            </section>

            {{-- Subtitle teman di atas adegan --}}
            <section class="relative isolate overflow-hidden border-t border-gray-800">
                @if ($still)
                    <img src="{{ $still->heroBackdropUrl() }}" alt="" loading="lazy" class="absolute inset-0 -z-20 h-full w-full object-cover">
                    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-layar via-layar/70 to-layar/40"></div>
                @endif

                <div class="mx-auto max-w-6xl px-4 py-24 sm:px-6 lg:px-8 lg:py-32">
                    <h2 class="max-w-xl font-condensed text-4xl font-bold text-white">Lihat kata temanmu sebelum menonton</h2>
                    <p class="mt-3 max-w-md leading-relaxed text-gray-300">
                        Review teman muncul seperti subtitle di atas adegan filmnya. Yang mengandung spoiler dilipat sampai kamu sendiri yang membukanya.
                    </p>

                    <figure class="mx-auto mt-24 max-w-2xl text-center lg:mt-32">
                        <blockquote class="subtitle text-2xl leading-snug sm:text-3xl">Endingnya bikin diem lama, sumpah.</blockquote>
                        <figcaption class="subtitle mt-1 text-sm font-medium">(Citra, ★ 9)</figcaption>
                    </figure>
                    <p class="mt-6 text-center text-xs text-gray-400">Contoh tampilan</p>
                </div>
            </section>

            {{-- Fitur lain, cukup sebagai daftar --}}
            <section class="border-t border-gray-800">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
                    <h2 class="font-condensed text-4xl font-bold text-white">Yang juga ada di dalamnya</h2>

                    <dl class="mt-10 grid gap-x-12 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="font-semibold text-gray-100">Film, series, dan anime</dt>
                            <dd class="mt-1 leading-relaxed text-gray-400">Satu pencarian untuk ketiganya, termasuk judul Korea dan Jepang dengan ejaan latinnya.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-100">Semua skor di satu tempat</dt>
                            <dd class="mt-1 leading-relaxed text-gray-400">Kursi Penuh berdampingan dengan TMDB, IMDb, dan Rotten Tomatoes.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-100">Tonton di mana</dt>
                            <dd class="mt-1 leading-relaxed text-gray-400">Platform streaming, sewa, atau beli yang tersedia di Indonesia untuk setiap judul.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-100">Diary dan watchlist</dt>
                            <dd class="mt-1 leading-relaxed text-gray-400">Semua yang sudah dan ingin kamu tonton, tercatat per tanggal.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-100">Bagikan favoritmu</dt>
                            <dd class="mt-1 leading-relaxed text-gray-400">Empat favorit jadi gambar siap unggah ke Instagram, WhatsApp, atau X.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-100">Insight mingguan</dt>
                            <dd class="mt-1 leading-relaxed text-gray-400">Ringkasan kebiasaan nontonmu dan rekomendasi, diperbarui tiap Senin.</dd>
                        </div>
                    </dl>
                </div>
            </section>

            @if ($popular->isNotEmpty())
                <section class="border-t border-gray-800">
                    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:px-8">
                        <h2 class="font-condensed text-4xl font-bold text-white">Sedang ramai minggu ini</h2>

                        <ul class="mt-8 grid grid-cols-3 gap-4 sm:grid-cols-6">
                            @foreach ($popular as $media)
                                <li>
                                    <div class="aspect-[2/3] overflow-hidden rounded-sm bg-kursi">
                                        <img src="{{ $media->poster_url }}" alt="Poster {{ $media->title }}" loading="lazy" class="h-full w-full object-cover">
                                    </div>
                                    <p class="mt-2 line-clamp-2 text-sm font-semibold text-gray-100">{{ $media->title }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif

            {{-- Ajakan terakhir --}}
            <section class="border-t border-gray-800">
                <div class="mx-auto max-w-6xl px-4 py-24 text-center sm:px-6 lg:px-8">
                    <h2 class="font-extra-condensed text-5xl font-extrabold leading-none tracking-tight text-white sm:text-6xl">Kursimu sudah disiapkan.</h2>
                    <div class="mt-8">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-md bg-perak px-6 py-3 font-semibold text-layar hover:bg-white">Buka beranda</a>
                        @else
                            <a href="{{ route('register') }}" class="rounded-md bg-perak px-6 py-3 font-semibold text-layar hover:bg-white">Daftar gratis</a>
                        @endauth
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-gray-800">
            <div class="mx-auto max-w-6xl px-4 py-8 text-xs leading-relaxed text-gray-500 sm:px-6 lg:px-8">
                <p>{{ $appName }} dibuat oleh Kravio.</p>
                <p class="mt-1">Data judul dari TMDB, AniList, dan Jikan. Ketersediaan streaming dari JustWatch.</p>
            </div>
        </footer>
    </body>
</html>
