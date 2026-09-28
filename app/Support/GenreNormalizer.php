<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Menyatukan nama genre dari berbagai sumber ke satu daftar berbahasa Indonesia.
 *
 * TMDB (id-ID) memberi "Aksi" dan "Aksi & Petualangan", AniList memberi "Action";
 * AniList juga menyelipkan tag ("Ninja", "Female Protagonist") ke kolom genre.
 * Untuk statistik hanya genre yang dikenal di sini yang dihitung.
 */
final class GenreNormalizer
{
    /**
     * Nama mentah (huruf kecil) => genre baku. Satu nama gabungan dari TMDB TV
     * bisa pecah jadi dua genre.
     *
     * @var array<string, list<string>>
     */
    private const MAP = [
        'aksi' => ['Aksi'],
        'action' => ['Aksi'],
        'petualangan' => ['Petualangan'],
        'adventure' => ['Petualangan'],
        'aksi & petualangan' => ['Aksi', 'Petualangan'],
        'action & adventure' => ['Aksi', 'Petualangan'],
        'animasi' => ['Animasi'],
        'animation' => ['Animasi'],
        'komedi' => ['Komedi'],
        'comedy' => ['Komedi'],
        'kejahatan' => ['Kejahatan'],
        'crime' => ['Kejahatan'],
        'dokumenter' => ['Dokumenter'],
        'documentary' => ['Dokumenter'],
        'drama' => ['Drama'],
        'keluarga' => ['Keluarga'],
        'family' => ['Keluarga'],
        'fantasi' => ['Fantasi'],
        'fantasy' => ['Fantasi'],
        'sci-fi & fantasy' => ['Fiksi Ilmiah', 'Fantasi'],
        'cerita fiksi' => ['Fiksi Ilmiah'],
        'fiksi ilmiah' => ['Fiksi Ilmiah'],
        'science fiction' => ['Fiksi Ilmiah'],
        'sci-fi' => ['Fiksi Ilmiah'],
        'sejarah' => ['Sejarah'],
        'history' => ['Sejarah'],
        'kengerian' => ['Horor'],
        'horor' => ['Horor'],
        'horror' => ['Horor'],
        'musik' => ['Musik'],
        'music' => ['Musik'],
        'misteri' => ['Misteri'],
        'mystery' => ['Misteri'],
        'percintaan' => ['Romantis'],
        'romantis' => ['Romantis'],
        'romance' => ['Romantis'],
        'film tv' => ['Film TV'],
        'tv movie' => ['Film TV'],
        'cerita seru' => ['Thriller'],
        'thriller' => ['Thriller'],
        'perang' => ['Perang'],
        'war' => ['Perang'],
        'war & politics' => ['Perang', 'Politik'],
        // Terjemahan TMDB untuk "War & Politics" yang meleset.
        'kejahatan dan politik' => ['Perang', 'Politik'],
        'barat' => ['Western'],
        'western' => ['Western'],
        'realitas' => ['Realitas'],
        'reality' => ['Realitas'],
        'anak-anak' => ['Anak-anak'],
        'kids' => ['Anak-anak'],
        'berita' => ['Berita'],
        'news' => ['Berita'],
        'bicara' => ['Talk Show'],
        'talk' => ['Talk Show'],
        'sabun' => ['Sinetron'],
        'soap' => ['Sinetron'],
        'psychological' => ['Psikologis'],
        'slice of life' => ['Slice of Life'],
        'sports' => ['Olahraga'],
        'supernatural' => ['Supernatural'],
        'mecha' => ['Mecha'],
        'mahou shoujo' => ['Mahou Shoujo'],
    ];

    /**
     * Genre baku => padanannya di tiap sumber, untuk halaman jelajah /genre/{slug}.
     * [id genre film TMDB, id genre TV TMDB, genre AniList]; null = tidak ada padanan.
     *
     * @var array<string, array{0: ?int, 1: ?int, 2: ?string}>
     */
    private const CATALOG = [
        'Aksi' => [28, 10759, 'Action'],
        'Petualangan' => [12, 10759, 'Adventure'],
        'Animasi' => [16, 16, null],
        'Komedi' => [35, 35, 'Comedy'],
        'Kejahatan' => [80, 80, null],
        'Dokumenter' => [99, 99, null],
        'Drama' => [18, 18, 'Drama'],
        'Keluarga' => [10751, 10751, null],
        'Fantasi' => [14, 10765, 'Fantasy'],
        'Fiksi Ilmiah' => [878, 10765, 'Sci-Fi'],
        'Sejarah' => [36, null, null],
        'Horor' => [27, null, 'Horror'],
        'Musik' => [10402, null, 'Music'],
        'Misteri' => [9648, 9648, 'Mystery'],
        'Romantis' => [10749, null, 'Romance'],
        'Film TV' => [10770, null, null],
        'Thriller' => [53, null, 'Thriller'],
        'Perang' => [10752, 10768, null],
        'Politik' => [null, 10768, null],
        'Western' => [37, 37, null],
        'Realitas' => [null, 10764, null],
        'Anak-anak' => [null, 10762, null],
        'Berita' => [null, 10763, null],
        'Talk Show' => [null, 10767, null],
        'Sinetron' => [null, 10766, null],
        'Psikologis' => [null, null, 'Psychological'],
        'Slice of Life' => [null, null, 'Slice of Life'],
        'Olahraga' => [null, null, 'Sports'],
        'Supernatural' => [null, null, 'Supernatural'],
        'Mecha' => [null, null, 'Mecha'],
        'Mahou Shoujo' => [null, null, 'Mahou Shoujo'],
    ];

    public static function slug(string $genre): string
    {
        return Str::slug($genre);
    }

    /**
     * @return array{name: string, slug: string, tmdb_movie: ?int, tmdb_tv: ?int, anilist: ?string}|null
     */
    public static function findBySlug(string $slug): ?array
    {
        foreach (self::CATALOG as $name => [$movie, $tv, $anilist]) {
            if (self::slug($name) === $slug) {
                return ['name' => $name, 'slug' => $slug, 'tmdb_movie' => $movie, 'tmdb_tv' => $tv, 'anilist' => $anilist];
            }
        }

        return null;
    }

    /**
     * @param  list<string>|null  $genres
     * @return list<string>
     */
    public static function normalize(?array $genres): array
    {
        $normalized = [];

        foreach ($genres ?? [] as $genre) {
            foreach (self::MAP[mb_strtolower(trim((string) $genre))] ?? [] as $canonical) {
                $normalized[$canonical] = true;
            }
        }

        return array_keys($normalized);
    }
}
