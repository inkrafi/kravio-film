<?php

namespace App\Models;

use App\Enums\MediaSource;
use App\Enums\MediaType;
use App\Support\GenreNormalizer;
use Database\Factories\MediaCacheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

#[Fillable([
    'external_id', 'source', 'media_type', 'slug', 'title', 'original_title',
    'poster_url', 'backdrop_url', 'synopsis', 'year', 'released_on',
    'genres', 'raw_payload', 'synced_at',
    'imdb_id', 'imdb_rating', 'imdb_votes', 'rotten_tomatoes_score',
    'tmdb_rating', 'tmdb_votes', 'credits', 'watch_providers', 'details_synced_at',
    'title_latin', 'original_title_latin', 'synopsis_id', 'localized_hash',
])]
class MediaCache extends Model
{
    /** @use HasFactory<MediaCacheFactory> */
    use HasFactory;

    protected $table = 'media_cache';

    protected function casts(): array
    {
        return [
            'source' => MediaSource::class,
            'media_type' => MediaType::class,
            'genres' => 'array',
            'raw_payload' => 'array',
            'released_on' => 'date',
            'synced_at' => 'datetime',
            'imdb_rating' => 'float',
            'imdb_votes' => 'integer',
            'rotten_tomatoes_score' => 'integer',
            'tmdb_rating' => 'float',
            'tmdb_votes' => 'integer',
            'credits' => 'array',
            'watch_providers' => 'array',
            'details_synced_at' => 'datetime',
        ];
    }

    public function hasExternalRatings(): bool
    {
        return $this->tmdb_rating !== null || $this->imdb_rating !== null || $this->rotten_tomatoes_score !== null;
    }

    /**
     * Orang di balik judul ini, dari kolom credits.
     *
     * @param  'directors'|'creators'|'cast'  $group
     * @return list<array{name: string, role: ?string, photo_url: ?string}>
     */
    public function people(string $group): array
    {
        return $this->credits[$group] ?? [];
    }

    /**
     * Platform tempat judul ini bisa ditonton, dari kolom watch_providers.
     *
     * @return list<array{id: int, name: string, logo_url: ?string, types: list<string>}>
     */
    public function watchProviders(): array
    {
        return $this->watch_providers['providers'] ?? [];
    }

    /**
     * Sinopsis berbahasa Indonesia kalau sudah diterjemahkan, selain itu sinopsis asli.
     */
    public function displaySynopsis(): ?string
    {
        return $this->synopsis_id ?: $this->synopsis;
    }

    /**
     * True kalau terjemahannya memang berbeda dari teks sumber.
     */
    public function hasTranslatedSynopsis(): bool
    {
        return filled($this->synopsis_id) && filled($this->synopsis)
            && trim($this->synopsis_id) !== trim($this->synopsis);
    }

    /**
     * Anime tidak punya media type sendiri (masuk Film/Series), tapi statistik
     * menghitungnya terpisah: semua judul dari AniList/Jikan, plus judul TMDB
     * bergenre Animasi yang bahasa aslinya Jepang.
     */
    public function isAnime(): bool
    {
        if (in_array($this->source, [MediaSource::Anilist, MediaSource::Jikan], true)) {
            return true;
        }

        return in_array('Animasi', $this->displayGenres(), true)
            && ($this->raw_payload['original_language'] ?? null) === 'ja';
    }

    /**
     * Genre dalam bahasa Indonesia; tag AniList yang bukan genre tidak ditampilkan.
     *
     * @return list<string>
     */
    public function displayGenres(): array
    {
        return GenreNormalizer::normalize($this->genres);
    }

    public function imdbUrl(): ?string
    {
        return $this->imdb_id ? "https://www.imdb.com/title/{$this->imdb_id}/" : null;
    }

    public function watchEntries(): HasMany
    {
        return $this->hasMany(WatchEntry::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function scopeOfType(Builder $query, MediaType|string $type): Builder
    {
        return $query->where('media_type', $type instanceof MediaType ? $type->value : $type);
    }

    /**
     * Kunci unik lintas sumber, mis. `tmdb-film-157336`. Dipakai untuk log dan
     * untuk mengenali tautan lama `/media/{kunci}`.
     */
    public function sourceKey(): string
    {
        return "{$this->source->value}-{$this->media_type->value}-{$this->external_id}";
    }

    /**
     * `/film/{slug}` atau `/series/{slug}`.
     */
    public function url(): string
    {
        return route('media.show', ['type' => $this->media_type->value, 'media' => $this]);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Slug hanya unik per media type, jadi judulnya dicari dengan tipe dari
     * segmen URL (`/film/…` atau `/series/…`). Dipakai binding route `{media}`.
     */
    public static function findBySlug(MediaType|string|null $type, string $slug): ?self
    {
        $type = $type instanceof MediaType ? $type : MediaType::tryFrom((string) $type);

        if (! $type) {
            return null;
        }

        return static::query()
            ->where('media_type', $type->value)
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Cari judul dari kunci URL lama `/media/{sumber}-{tipe}-{id}`, termasuk
     * `anilist-anime-…`/`jikan-anime-…` dari sebelum anime dilebur ke
     * Film/Series (kedua sumber itu hanya berisi anime, jadi sumber + id cukup).
     */
    public static function findByLegacyKey(string $key): ?self
    {
        $parts = explode('-', $key, 3);

        if (count($parts) !== 3 || ! MediaSource::tryFrom($parts[0])) {
            return null;
        }

        [$source, $mediaType, $externalId] = $parts;

        return static::query()
            ->where('source', $source)
            ->where('external_id', $externalId)
            ->when($mediaType !== 'anime', fn (Builder $query) => $query->where('media_type', $mediaType))
            ->first();
    }

    /**
     * Beri slug sekali saja; slug tidak ikut berubah walau judulnya diperbarui,
     * supaya tautan yang sudah dibagikan tetap hidup.
     */
    public function assignSlug(): void
    {
        if ($this->slug !== null) {
            return;
        }

        foreach ($this->slugCandidates() as $candidate) {
            $taken = static::query()
                ->where('media_type', $this->media_type->value)
                ->where('slug', $candidate)
                ->whereKeyNot($this->getKey())
                ->exists();

            if ($taken) {
                continue;
            }

            try {
                $this->forceFill(['slug' => $candidate])->save();

                return;
            } catch (UniqueConstraintViolationException) {
                // Pencarian lain baru saja memakai slug ini; coba kandidat berikutnya.
                $this->slug = null;
            }
        }
    }

    /**
     * `interstellar`, lalu `interstellar-2014`, lalu `interstellar-2014-2`, dst.
     *
     * @return \Generator<int, string>
     */
    private function slugCandidates(): \Generator
    {
        $base = Str::limit(
            Str::slug($this->title) ?: Str::slug((string) $this->original_title) ?: $this->sourceKey(),
            80,
            '',
        );
        $base = rtrim($base, '-');

        yield $base;

        $withYear = $this->year ? "{$base}-{$this->year}" : $base;

        if ($withYear !== $base) {
            yield $withYear;
        }

        for ($i = 2; $i < 100; $i++) {
            yield "{$withYear}-{$i}";
        }

        yield $this->sourceKey();
    }

    protected static function booted(): void
    {
        // Upsert di MediaSearchService melewati event ini; di sana slug
        // diberikan terpisah setelah upsert.
        static::created(fn (MediaCache $media) => $media->assignSlug());
    }
}
