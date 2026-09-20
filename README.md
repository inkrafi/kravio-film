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
```

Lalu:

```bash
php artisan migrate
npm run build      # atau `npm run dev` saat mengembangkan
php artisan serve
```

`GEMINI_API_KEY` baru dipakai di fase 4 dan boleh dibiarkan kosong.
Tanpa `TMDB_API_KEY`, pencarian tetap jalan tapi hanya mengembalikan anime — UI
menampilkan catatan bahwa sumber TMDB dilewati.

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
  │            AniListProvider  → POST graphql.anilist.co   (anime)
  └─ cadangan  JikanProvider    → GET  /anime?q=            (anime)
```

- Provider tidak menembak HTTP sendiri; ia hanya mendeskripsikan request
  (`ProviderRequest`) dan menerjemahkan response-nya. `MediaSearchService`
  menjalankan semuanya paralel lewat `Http::pool()`.
- **Fallback otomatis**: kalau sebuah media type tidak terlayani sama sekali karena
  sumber utamanya mati, ronde kedua menjalankan sumber cadangan untuk media type itu.
  Jikan dipakai sebagai cadangan anime kalau AniList gagal — dan sebaliknya, AniList
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

## Status fase

- [x] **Fase 1 — Fondasi**: auth + `username`/`bio`, skema database lengkap,
      integrasi TMDB + AniList (+ cadangan Jikan), komponen Livewire search gabungan.
- [ ] Fase 2 — Interaksi inti (watched/watchlist/rating/review, profil, pertemanan)
- [ ] Fase 3 — Sosial (komentar profil, diary, statistik)
- [ ] Fase 4 — AI (Gemini, insight mingguan, rekomendasi)
- [ ] Fase 5 — Polish (UI final, QA, deploy)
