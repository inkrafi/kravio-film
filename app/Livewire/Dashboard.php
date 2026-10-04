<?php

namespace App\Livewire;

use App\Models\MediaCache;
use App\Models\Review;
use App\Models\WatchEntry;
use App\Services\FriendshipService;
use App\Services\Insights\InsightService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Beranda setelah login: apa yang baru ditonton teman, watchlist sendiri,
 * dan rekomendasi dari insight terakhir. Semuanya hanya membaca data yang
 * sudah ada, tanpa request ke API luar.
 */
#[Layout('layouts.app')]
#[Title('Beranda')]
class Dashboard extends Component
{
    public const ACTIVITY_LIMIT = 12;

    public const WATCHLIST_LIMIT = 6;

    public const RECOMMENDATION_LIMIT = 6;

    public function render(FriendshipService $friendships, InsightService $insights)
    {
        $user = Auth::user();
        $friendIds = $friendships->friendIdsOf($user);
        $activity = $this->friendActivity($friendIds);

        return view('livewire.dashboard', [
            'user' => $user,
            'hasFriends' => $friendIds !== [],
            'activity' => $activity,
            'reviews' => $this->reviewsFor($activity),
            'watchlist' => WatchEntry::query()
                ->where('user_id', $user->id)
                ->watchlist()
                ->with('media')
                ->latest('updated_at')
                ->limit(self::WATCHLIST_LIMIT)
                ->get(),
            'watchlistCount' => WatchEntry::query()->where('user_id', $user->id)->watchlist()->count(),
            'watchedCount' => WatchEntry::query()->where('user_id', $user->id)->watched()->count(),
            'recommendations' => $this->recommendations($insights),
            'incomingRequests' => $friendships->incomingRequests($user)->count(),
        ]);
    }

    /**
     * Tontonan terbaru teman. Pustaka teman memang terbuka untuk sesama teman
     * (lihat UserPolicy::viewLibrary), jadi tidak perlu cek per baris.
     *
     * @param  list<int>  $friendIds
     * @return Collection<int, WatchEntry>
     */
    private function friendActivity(array $friendIds): Collection
    {
        if ($friendIds === []) {
            return collect();
        }

        return WatchEntry::query()
            ->whereIn('user_id', $friendIds)
            ->watched()
            ->whereNotNull('watched_at')
            ->with(['user', 'media'])
            ->latest('watched_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();
    }

    /**
     * Review untuk aktivitas di atas, dikunci per "user_id-media_cache_id".
     *
     * @param  Collection<int, WatchEntry>  $activity
     * @return Collection<string, Review>
     */
    private function reviewsFor(Collection $activity): Collection
    {
        if ($activity->isEmpty()) {
            return collect();
        }

        return Review::query()
            ->whereIn('user_id', $activity->pluck('user_id')->unique())
            ->whereIn('media_cache_id', $activity->pluck('media_cache_id')->unique())
            ->get()
            ->keyBy(fn (Review $review) => "{$review->user_id}-{$review->media_cache_id}");
    }

    /**
     * Rekomendasi dari insight terakhir, tanpa judul yang sudah dicatat sejak itu.
     *
     * @return Collection<int, MediaCache>
     */
    private function recommendations(InsightService $insights): Collection
    {
        $ids = collect($insights->latest(Auth::user())?->content['recommendations'] ?? [])->pluck('media_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $logged = WatchEntry::query()
            ->where('user_id', Auth::id())
            ->whereIn('media_cache_id', $ids)
            ->pluck('media_cache_id');

        $media = MediaCache::query()->whereIn('id', $ids->diff($logged))->get()->keyBy('id');

        // Urutan dari insight dipertahankan.
        return $ids->map(fn ($id) => $media->get($id))
            ->filter()
            ->take(self::RECOMMENDATION_LIMIT)
            ->values();
    }
}
