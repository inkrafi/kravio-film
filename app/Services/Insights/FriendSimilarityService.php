<?php

namespace App\Services\Insights;

use App\Models\User;
use App\Models\WatchEntry;
use App\Services\FriendshipService;
use App\Services\ProfileStatsService;

/**
 * Kesamaan selera dengan teman, dihitung tanpa AI:
 *
 * - kemiripan = cosine similarity antar profil genre (jumlah judul per genre);
 * - judul yang sama-sama ditonton;
 * - "pilihan teman": judul yang ia rating tinggi tapi belum ada di daftarmu.
 *
 * Hanya teman yang sudah diterima — sama dengan aturan visibilitas riwayat.
 */
class FriendSimilarityService
{
    public const MAX_FRIENDS = 5;

    private const MIN_WATCHED = 3;

    private const PICK_MIN_RATING = 8;

    private const MAX_PICKS = 4;

    public function __construct(
        private readonly FriendshipService $friendships,
        private readonly ProfileStatsService $stats,
    ) {}

    /**
     * @return list<array{user_id: int, similarity: float, shared_genres: list<string>, shared_titles: int, picks: list<array{media_id: int, rating: int}>}>
     */
    public function for(User $user): array
    {
        $mine = $this->stats->genreCounts($user);

        if ($mine === []) {
            return [];
        }

        $myTitles = $user->watchEntries()->pluck('media_cache_id')->all();
        $myWatched = $user->watchEntries()->watched()->pluck('media_cache_id')->all();

        $friends = User::query()->whereIn('id', $this->friendships->friendIdsOf($user))->get();

        return $friends
            ->map(function (User $friend) use ($mine, $myTitles, $myWatched) {
                $theirs = $this->stats->genreCounts($friend);
                $theirWatched = $friend->watchEntries()->watched()->pluck('media_cache_id')->all();

                if (count($theirWatched) < self::MIN_WATCHED) {
                    return null;
                }

                return [
                    'user_id' => $friend->id,
                    'similarity' => round($this->cosine($mine, $theirs), 3),
                    'shared_genres' => array_slice(array_keys(array_intersect_key(
                        array_slice($mine, 0, 5, true),
                        array_slice($theirs, 0, 5, true),
                    )), 0, 3),
                    'shared_titles' => count(array_intersect($myWatched, $theirWatched)),
                    'picks' => WatchEntry::query()
                        ->where('user_id', $friend->id)
                        ->watched()
                        ->where('rating', '>=', self::PICK_MIN_RATING)
                        ->whereNotIn('media_cache_id', $myTitles)
                        ->orderByDesc('rating')
                        ->orderByDesc('watched_at')
                        ->take(self::MAX_PICKS)
                        ->get(['media_cache_id', 'rating'])
                        ->map(fn (WatchEntry $entry) => ['media_id' => $entry->media_cache_id, 'rating' => $entry->rating])
                        ->all(),
                ];
            })
            ->filter()
            ->sortByDesc('similarity')
            ->take(self::MAX_FRIENDS)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, int>  $a
     * @param  array<string, int>  $b
     */
    private function cosine(array $a, array $b): float
    {
        $dot = 0;

        foreach ($a as $genre => $count) {
            $dot += $count * ($b[$genre] ?? 0);
        }

        $norm = fn (array $vector) => sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector)));
        $denominator = $norm($a) * $norm($b);

        return $denominator > 0 ? $dot / $denominator : 0.0;
    }
}
