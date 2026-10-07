<?php

namespace App\Services\Insights;

use App\Models\AiInsight;
use App\Models\MediaCache;
use App\Models\Review;
use App\Models\User;
use App\Services\Ai\GeminiClient;
use App\Services\FriendshipService;
use App\Services\ProfileStatsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Insight kebiasaan nonton mingguan: ringkasan dari Gemini, rekomendasi
 * personal (dipilih Gemini dari kandidat nyata), dan kesamaan dengan teman.
 *
 * Hasilnya disimpan di ai_insights dan hanya dibuat ulang lewat jadwal
 * mingguan (kalau datanya berubah) atau tombol manual yang dibatasi sehari
 * sekali — tidak pernah saat profil sekadar dibuka.
 */
class InsightService
{
    public const MIN_WATCHED = 3;

    public const MAX_RECOMMENDATIONS = 8;

    /** Jeda minimal antar-pembaruan manual. */
    public const MANUAL_COOLDOWN_HOURS = 24;

    /** Jadwal mingguan melewati insight yang lebih muda dari ini. */
    public const SCHEDULE_MIN_AGE_DAYS = 6;

    private const INSTRUCTION = <<<'TEXT'
        Kamu adalah teman nonton yang jeli untuk aplikasi Kursi Penuh (pencatat dan rating film & series ala Letterboxd).
        Tulis dalam bahasa Indonesia yang santai tapi rapi, sapa pengguna dengan "kamu". Jangan pakai emoji.

        Dari data tontonan yang diberikan, buat:
        - headline: satu kalimat pendek (maks. 12 kata) yang menangkap selera pengguna.
        - summary: 2 paragraf pendek (dipisah baris kosong) tentang pola tontonan: genre, tipe (film/series), kebiasaan memberi rating, ritme menonton, dan hal menarik dari review-nya. Sebut angka dari data seperlunya. Jangan mengarang fakta yang tidak ada di data.
        - highlights: 3 sampai 4 poin singkat, masing-masing punya label (2-4 kata) dan text (satu kalimat).
        - recommendations: pilih paling banyak 8 judul HANYA dari "candidates" (pakai id-nya persis). Utamakan yang paling cocok dengan selera dan variasikan film/series. Untuk tiap judul beri reason satu kalimat yang menghubungkannya dengan tontonan pengguna.
        TEXT;

    private const SCHEMA = [
        'type' => 'OBJECT',
        'properties' => [
            'headline' => ['type' => 'STRING'],
            'summary' => ['type' => 'STRING'],
            'highlights' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['label' => ['type' => 'STRING'], 'text' => ['type' => 'STRING']],
                    'required' => ['label', 'text'],
                ],
            ],
            'recommendations' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['id' => ['type' => 'INTEGER'], 'reason' => ['type' => 'STRING']],
                    'required' => ['id', 'reason'],
                ],
            ],
        ],
        'required' => ['headline', 'summary', 'highlights', 'recommendations'],
    ];

    public function __construct(
        private readonly GeminiClient $gemini,
        private readonly ProfileStatsService $stats,
        private readonly RecommendationService $recommendations,
        private readonly FriendSimilarityService $friends,
        private readonly FriendshipService $friendships,
    ) {}

    public function isConfigured(): bool
    {
        return $this->gemini->isConfigured();
    }

    public function latest(User $user): ?AiInsight
    {
        return $user->aiInsights()->latest('generated_at')->latest('id')->first();
    }

    public function hasEnoughData(User $user): bool
    {
        return $user->watchEntries()->watched()->count() >= self::MIN_WATCHED;
    }

    /**
     * Jadwal mingguan: belum pernah dibuat, atau sudah cukup tua dan datanya berubah.
     */
    public function isDue(User $user): bool
    {
        if (! $this->hasEnoughData($user)) {
            return false;
        }

        $latest = $this->latest($user);

        return $latest === null
            || ($latest->generated_at->lte(now()->subDays(self::SCHEDULE_MIN_AGE_DAYS))
                && ($latest->content['fingerprint'] ?? null) !== $this->fingerprint($user));
    }

    public function canRefreshManually(User $user): bool
    {
        $latest = $this->latest($user);

        return $this->hasEnoughData($user)
            && ($latest === null || $latest->generated_at->lte(now()->subHours(self::MANUAL_COOLDOWN_HOURS)));
    }

    /**
     * Sidik jari data yang memengaruhi insight: riwayat tontonan, review, dan teman.
     */
    public function fingerprint(User $user): string
    {
        return sha1(json_encode([
            $user->watchEntries()->count(),
            $user->watchEntries()->max('updated_at'),
            Review::where('user_id', $user->id)->count(),
            Review::where('user_id', $user->id)->max('updated_at'),
            $this->friendships->friendIdsOf($user),
        ]));
    }

    /**
     * @throws \RuntimeException kalau Gemini gagal (job akan mencoba ulang).
     */
    public function generate(User $user): ?AiInsight
    {
        if (! $this->hasEnoughData($user)) {
            return null;
        }

        $candidates = $this->recommendations->candidates($user);

        $result = $this->gemini->generateJson(
            self::INSTRUCTION,
            json_encode($this->promptData($user, $candidates), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            self::SCHEMA,
            temperature: 0.7,
        );

        return $user->aiInsights()->create([
            // Bisa model cadangan kalau kuota harian model utama habis.
            'model' => $this->gemini->lastModel ?? (string) config('services.gemini.model'),
            'generated_at' => now(),
            'content' => [
                'version' => 1,
                'headline' => Str::limit(trim((string) ($result['headline'] ?? '')), 160),
                'summary' => trim((string) ($result['summary'] ?? '')),
                'highlights' => collect($result['highlights'] ?? [])
                    ->filter(fn ($item) => filled($item['label'] ?? null) && filled($item['text'] ?? null))
                    ->take(4)
                    ->map(fn ($item) => ['label' => trim($item['label']), 'text' => trim($item['text'])])
                    ->values()
                    ->all(),
                'recommendations' => $this->validRecommendations($result['recommendations'] ?? [], $candidates),
                'friends' => $this->friends->for($user),
                'fingerprint' => $this->fingerprint($user),
            ],
        ]);
    }

    /**
     * Hanya id yang ada di daftar kandidat yang dipakai; kalau Gemini memilih
     * terlalu sedikit, sisanya diisi kandidat dengan skor tertinggi.
     *
     * @param  list<array{id?: mixed, reason?: mixed}>  $picked
     * @param  Collection<int, array{media: MediaCache, because: ?string, score: float}>  $candidates
     * @return list<array{media_id: int, reason: ?string}>
     */
    private function validRecommendations(array $picked, Collection $candidates): array
    {
        $byId = $candidates->keyBy(fn (array $candidate) => $candidate['media']->id);

        $chosen = collect($picked)
            ->filter(fn ($item) => is_numeric($item['id'] ?? null) && $byId->has((int) $item['id']))
            ->unique(fn ($item) => (int) $item['id'])
            ->take(self::MAX_RECOMMENDATIONS)
            ->map(fn ($item) => ['media_id' => (int) $item['id'], 'reason' => filled($item['reason'] ?? null) ? trim($item['reason']) : null])
            ->values();

        $fill = $byId
            ->reject(fn (array $candidate, int $id) => $chosen->contains('media_id', $id))
            ->take(max(0, min(4, self::MAX_RECOMMENDATIONS) - $chosen->count()))
            ->map(fn (array $candidate, int $id) => [
                'media_id' => $id,
                'reason' => $candidate['because'] ? 'Karena kamu menyukai '.$candidate['because'].'.' : null,
            ]);

        return $chosen->concat($fill->values())->all();
    }

    /**
     * Data yang dikirim ke Gemini: ringkasan riwayat milik pengguna sendiri.
     * Data teman tidak ikut dikirim — bagian teman dihitung tanpa AI.
     *
     * @param  Collection<int, array{media: MediaCache, because: ?string, score: float}>  $candidates
     * @return array<string, mixed>
     */
    private function promptData(User $user, Collection $candidates): array
    {
        $stats = $this->stats->for($user);

        $watched = $user->watchEntries()->watched()->with('media')->get();

        $describe = fn ($entry) => [
            'title' => $entry->media->title_latin ?? $entry->media->title,
            'year' => $entry->media->year,
            'type' => $entry->media->media_type->label(),
            'rating' => $entry->rating,
        ];

        $reviews = Review::with('media')
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn (Review $review) => [
                'title' => $review->media->title_latin ?? $review->media->title,
                'excerpt' => Str::limit($review->body, 300),
            ]);

        return [
            'stats' => [
                'total_watched' => $stats['total'],
                'films' => $stats['films'],
                'series' => $stats['series'],
                'anime' => $stats['anime'],
                'watched_this_year' => $stats['this_year'],
                'average_rating' => $stats['average_rating'],
                'rated_count' => $stats['rated'],
                'watchlist_count' => $user->watchEntries()->watchlist()->count(),
                'top_genres' => array_map(fn ($genre) => ['genre' => $genre['name'], 'titles' => $genre['count']], $stats['genres']),
                'rating_distribution' => $watched->whereNotNull('rating')->countBy('rating')->sortKeys()->all(),
                'watched_per_month_last_6' => collect(range(5, 0))
                    ->mapWithKeys(fn (int $ago) => [
                        now()->subMonthsNoOverflow($ago)->format('Y-m') => $watched
                            ->filter(fn ($entry) => $entry->watched_at?->format('Y-m') === now()->subMonthsNoOverflow($ago)->format('Y-m'))
                            ->count(),
                    ])
                    ->all(),
            ],
            'highest_rated' => $watched->whereNotNull('rating')->sortByDesc('rating')->take(5)->map($describe)->values()->all(),
            'lowest_rated' => $watched->whereNotNull('rating')->sortBy('rating')->take(3)->map($describe)->values()->all(),
            'recently_watched' => $watched->sortByDesc('watched_at')->take(5)->map($describe)->values()->all(),
            'recent_reviews' => $reviews->all(),
            'candidates' => $candidates->map(fn (array $candidate) => [
                'id' => $candidate['media']->id,
                'title' => $candidate['media']->title_latin ?? $candidate['media']->title,
                'year' => $candidate['media']->year,
                'type' => $candidate['media']->media_type->label(),
                'genres' => $candidate['media']->displayGenres(),
                'similar_to' => $candidate['because'],
            ])->all(),
        ];
    }
}
