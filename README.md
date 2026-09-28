# Kravio

Platform pencatat, rating, dan review film / series / anime bergaya Letterboxd, dengan
sistem pertemanan yang mengatur visibilitas riwayat tontonan dan insight AI mingguan.

Spesifikasi lengkap ada di `PRD – Platform Tracking Film, Series & Anime (ala Letterboxd Indonesia).md`.

## Stack

| Komponen | Pilihan |
| --- | --- |
| Backend | Laravel 13 (PHP 8.4) |
| Database | PostgreSQL 17 |
| Frontend | Livewire 3 + Volt + Alpine.js + Tailwind CSS 3 |
| Auth | Laravel Breeze (stack Livewire, dark mode) |
| Data film & series | TMDB API |
| Data anime | AniList GraphQL, cadangan: Jikan API (wrapper MyAnimeList) — keduanya tanpa API key |
| Insight AI | Gemini API (model Flash) — fase 4 |

## Setup lokal

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Buat dua database (yang kedua khusus untuk test):

```bash
createdb kravio
createdb kravio_testing
```

Isi kredensial di `.env`:

```ini
DB_CONNECTION=pgsql
DB_DATABASE=kravio
DB_USERNAME=postgres
DB_PASSWORD=...

TMDB_API_KEY=...        # https://www.themoviedb.org/settings/api (v3 key atau v4 token)
TMDB_LANGUAGE=id-ID
OMDB_API_KEY=...        # https://www.omdbapi.com/apikey.aspx — rating IMDb & Rotten Tomatoes (opsional)
```

Lalu:

```bash
php artisan migrate
php artisan storage:link   # foto profil disajikan dari /storage
npm run build      # atau `npm run dev` saat mengembangkan
php artisan serve
```

`GEMINI_API_KEY` baru dipakai di fase 4 dan boleh dibiarkan kosong.
Tanpa `TMDB_API_KEY`, pencarian tetap jalan tapi hanya mengembalikan anime — UI
menampilkan catatan bahwa sumber TMDB dilewati. Tanpa `OMDB_API_KEY`, rating IMDb &
Rotten Tomatoes tidak ditampilkan dan anime tidak punya rating/pemain (anime ditautkan ke
TMDB lewat IMDb ID dari OMDb); rating TMDB, pemain, dan sutradara judul TMDB tetap tampil.

Judul, nama, dan sinopsis dilokalkan untuk pembaca Indonesia:

- Judul beraksara non-latin (Korea/Jepang/Mandarin/dll.) punya versi latin (`title_latin`,
  `original_title_latin`). Saat pencarian diisi dari judul en-US TMDB atau romaji
  AniList/Jikan, dengan romanisasi offline (`App\Support\Romanizer`) sebagai cadangan.
- Di halaman detail, `MediaLocalizationService` meminta Gemini (satu request per judul)
  menerjemahkan sinopsis ke bahasa Indonesia, memberi judul latin resmi, dan ejaan latin
  nama sutradara/kreator/pemain (mis. 이선균 → Lee Sun-kyun). Hasilnya disimpan permanen
  dan hanya diulang kalau teks sumbernya berubah; sinopsis asli tetap bisa dibuka.
- Tanpa `GEMINI_API_KEY`, sinopsis tampil dalam bahasa sumbernya dan hanya romanisasi
  offline yang dipakai. Genre selalu ditampilkan dalam bahasa Indonesia.

Insight AI (`App\Services\Insights`):

- Dibuat oleh job `GenerateUserInsight` di queue — jalankan `php artisan queue:work`, dan
  `php artisan schedule:work` (atau cron `schedule:run`) untuk jadwal mingguan. Job dibatasi
  8 panggilan Gemini/menit supaya muat di kuota gratis.
- Jadwal Senin hanya membuat ulang insight yang umurnya >= 6 hari **dan** datanya berubah
  (riwayat, review, atau teman); tombol manual di `/insight` dibatasi sekali per 24 jam.
  `php artisan kravio:insights --user=budi --force` untuk membuat satu insight langsung.
- Rekomendasi tidak dikarang AI: kandidatnya judul nyata dari rekomendasi TMDB untuk judul
  yang kamu rating >= 8, ditambah judul populer di genre favoritmu. Gemini memilih dan
  menjelaskan; id di luar daftar kandidat dibuang.
- Kuota gratis Gemini dihitung **per model per hari** (gemini-3.8-flash: 20 permintaan/hari).
  Default-nya model ringan `gemini-3.1-flash-lite` untuk insight (`GEMINI_MODEL`) dan terjemahan
  (`GEMINI_TRANSLATION_MODEL`); kalau kuota hariannya habis, permintaan dialihkan sekali ke
  `GEMINI_FALLBACK_MODEL` (`gemini-3.8-flash`) — harus model lain supaya ada kuota kedua.
  Kuota harian yang habis tidak dicoba ulang oleh job.
- Setelah mengubah kode, jalankan `php artisan queue:restart` — worker menyimpan kode lama di memori.
- Kesamaan dengan teman dihitung tanpa AI (cosine similarity profil genre) dan data teman
  tidak pernah dikirim ke Gemini. Hanya teman yang sudah diterima yang ikut dihitung.

List (`MediaList`, `MediaListService`, `MediaListPolicy`):

- Visibilitas dipilih per list: Publik, Teman saja, atau Pribadi. List yang tidak boleh
  dilihat membalas 404 supaya keberadaannya tidak bocor.
- Judul ditambahkan dari tombol "Tambah ke list" di halaman detail atau kolom cari di
  halaman ubah list. Urutan diubah dengan seret-lepas (SortableJS, lihat `resources/js/app.js`)
  atau tombol ↑ ↓; disimpan rapat (1, 2, 3, …). Catatan per judul maks. 500 karakter.
- Di profil, List adalah tab di sebelah Diary. Tab ini muncul juga untuk yang belum berteman
  (hanya berisi list yang boleh mereka lihat), sementara tab riwayat tetap terkunci.
- Batas: 100 list per pengguna, 500 judul per list.
- Menyalin list orang lain menghasilkan list **pribadi** baru tanpa catatan pemilik aslinya.
- Like dan salin hanya untuk list orang lain; komentar untuk siapa pun yang bisa melihat list,
  dan pemilik list boleh menghapus komentar apa pun di list-nya.

Foto profil diunggah di `/profile` (JPG/PNG/WebP/GIF, maks. 2 MB), lalu dipotong persegi,
diperkecil ke 256 px, dan disimpan ulang sebagai WebP — metadata EXIF seperti lokasi GPS
ikut terbuang. Disk-nya diatur lewat `AVATAR_DISK` (default `public`). Di Railway
filesystem-nya sementara, jadi arahkan `AVATAR_DISK` ke disk S3/R2 supaya foto tidak
hilang tiap redeploy.

Halaman detail mengambil rating TMDB/IMDb/Rotten Tomatoes serta pemain & sutradara
setelah halaman tampil (`MediaDetailsService`), lalu menyimpannya di `media_cache`
selama seminggu. Kartu hasil pencarian sengaja tidak menampilkan rating.

## Testing

```bash
php artisan test
```

Test memakai database `kravio_testing` di PostgreSQL (bukan SQLite in-memory), supaya
`CHECK` constraint dan `upsert` ikut teruji. Semua panggilan HTTP di-fake.

## Arsitektur pencarian media

```
MediaSearchService
  ├─ utama     TmdbProvider     → GET  /search/multi        (film + series)
  │            AniListProvider  → POST graphql.anilist.co   (anime → film + series)
  └─ cadangan  JikanProvider    → GET  /anime?q=            (anime → film + series)
```

- Hanya ada dua media type: **Film** dan **Series**. Anime tidak punya tipe sendiri —
  anime movie (AniList `format: MOVIE`, Jikan `type: Movie`) masuk Film, sisanya
  (TV, OVA, ONA, special, ...) masuk Series.

- Provider tidak menembak HTTP sendiri; ia hanya mendeskripsikan request
  (`ProviderRequest`) dan menerjemahkan response-nya. `MediaSearchService`
  menjalankan semuanya paralel lewat `Http::pool()`.
- **Fallback otomatis**: kalau sebuah sumber utama gagal, ronde kedua menjalankan
  sumber cadangannya. Jikan dipakai sebagai cadangan kalau AniList gagal — dan sebaliknya, AniList
  dijadikan sumber utama justru karena endpoint pencarian Jikan cukup sering membalas
  `504` saat MyAnimeList tidak bisa dihubungi dari sisi mereka. Sumber yang tertutupi
  cadangan tidak dilaporkan sebagai gagal — UI hanya memberi tahu "AniList sedang
  bermasalah, hasil diambil dari MyAnimeList". Cadangan tidak dijalankan kalau sumber
  utamanya sehat tapi memang tidak ada hasil.
- Hasil dinormalisasi ke `MediaResult`, di-upsert ke tabel `media_cache`
  (unik per `source` + `media_type` + `external_id`), lalu diurutkan berdasarkan
  kemiripan judul (bobot 0.75) dan popularitas relatif per sumber (0.25).
- Hasil sukses dicache 10 menit; hasil yang sebagian sumbernya gagal hanya 30 detik,
  supaya gangguan singkat di API eksternal tidak mengunci pencarian.
- Satu sumber yang mati tidak menggagalkan pencarian — sumbernya dilaporkan lewat
  `failedSources` dan ditampilkan sebagai peringatan di UI.

Menambah atau menukar sumber cukup membuat kelas yang mengimplementasikan
`App\Contracts\MediaProvider`, lalu mendaftarkannya di `AppServiceProvider::PRIMARY`
atau `::FALLBACK`. Menukar peran dua sumber cukup dengan memindahkan nama kelasnya
antara kedua konstanta itu — tidak ada perubahan lain yang perlu.

## Halaman

| URL | Isi |
| --- | --- |
| `/search` | Pencarian gabungan film & series (termasuk anime) |
| `/film/{slug}`, `/series/{slug}` | Detail satu judul + semua aksi pribadi atas judul itu |
| `/orang/{id}-{nama}` | Profil sutradara/kreator/pemain (TMDB) + semua judul yang ia garap dan bintangi |
| `/genre/{slug}` | Judul populer satu genre: TMDB discover (film/series) + AniList (anime), tab Film/Series |
| `/insight` | Insight AI pribadi: ringkasan kebiasaan nonton, rekomendasi, kesamaan dengan teman |
| `/u/{username}?daftar=list` | Tab List di profil (URL lama `/u/{username}/list` dialihkan ke sini) |
| `/list/baru`, `/list/{id}/ubah` | Buat/ubah list: detail, cari & tambah judul, urutkan, catatan per judul |
| `/list/{id}-{slug}` | Satu list: isi berurutan (+ nomor untuk list peringkat), like, salin, komentar |
| `/u/{username}` | Profil publik: bio, favorit, watched/watchlist |
| `/teman` | Permintaan masuk/terkirim, daftar teman, cari pengguna |
| `/profile` | Pengaturan akun bawaan Breeze (nama, username, bio, email, password) |

Slug dibuat dari judul (mis. `/film/interstellar`) dan unik per tipe, jadi film dan
series boleh bernama sama. Judul kembar diberi tahun, lalu nomor: `dune`, `dune-1984`,
`dune-1984-2`. Slug diberikan sekali saat judul pertama kali tersimpan dan tidak
berubah walau judulnya diperbarui. Tautan lama `/media/tmdb-film-157336` dialihkan
permanen (301) ke URL baru.

## Aturan visibilitas

Bio dan favorit terbuka untuk siapa pun yang sudah login — itu etalase selera.
Watched, watchlist, diary, statistik, dan komentar profil hanya terlihat oleh diri
sendiri dan teman yang permintaannya sudah **diterima**; permintaan yang masih pending belum membuka apa pun.
Arah permintaan tidak berpengaruh: siapa pun yang dulu mengirim, keduanya sama-sama
bisa melihat dan sama-sama bisa memutuskan pertemanan.

Aturan ini dijaga `UserPolicy::viewLibrary()` supaya tidak bergantung pada komponen
yang kebetulan merendernya.

Komentar profil: teman dan pemilik profil boleh menulis (maks. 1.000 karakter,
5 komentar/menit). Penulis boleh menghapus komentarnya sendiri; pemilik profil boleh
menghapus komentar apa pun di profilnya (`ProfileCommentPolicy`).

Genre di statistik disatukan lewat `App\Support\GenreNormalizer`: nama dari TMDB
(Indonesia) dan AniList (Inggris) dipetakan ke satu daftar, dan tag AniList yang bukan
genre (mis. "Ninja") tidak dihitung. Anime dari AniList/Jikan otomatis dihitung
sebagai Animasi, sama seperti di TMDB.

## Data contoh

Fitur pertemanan butuh lebih dari satu akun untuk dicoba manual:

```bash
php artisan db:seed --class=DemoSeeder
```

Seeder memakai judul yang sudah ada di `media_cache`, jadi lakukan beberapa pencarian
dulu. Akun yang dibuat: `budi`, `citra`, `dimas` — password `password`.

## Status fase

- [x] **Fase 1 — Fondasi**: auth + `username`/`bio`, skema database lengkap,
      integrasi TMDB + AniList (+ cadangan Jikan), komponen Livewire search gabungan.
- [x] **Fase 2 — Interaksi inti**: halaman detail media, watched/watchlist,
      rating 1-10, review, favorit, profil publik, dan pertemanan dengan gating.
- [x] **Fase 3 — Sosial**: komentar profil (terkirim tanpa reload, komentar baru
      muncul lewat polling 15 detik), diary kronologis per bulan, dan statistik profil
      (total ditonton, rata-rata rating, genre favorit, tontonan tahun ini).
- [x] **Fase 4 — AI**: insight kebiasaan nonton (Gemini) di `/insight`, dibuat lewat queue
      tiap Senin 06.00 WIB (`kravio:insights`) atau manual maks. sehari sekali;
      rekomendasi personal dan kesamaan selera dengan teman.
- [ ] Fase 5 — Polish (UI final, QA, deploy)
