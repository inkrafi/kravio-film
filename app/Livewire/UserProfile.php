<?php

namespace App\Livewire;

use App\Enums\WatchStatus;
use App\Models\Favorite;
use App\Models\MediaList;
use App\Models\Review;
use App\Models\User;
use App\Services\FavoriteShareImageService;
use App\Services\FriendshipService;
use App\Services\ProfileStatsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Profil publik seorang pengguna.
 *
 * Bio dan favorit selalu terlihat; watched, watchlist, diary, statistik, dan
 * komentar hanya terbuka untuk diri sendiri dan teman yang sudah saling menerima.
 */
#[Layout('layouts.app')]
class UserProfile extends Component
{
    use WithPagination;

    public const DIARY = 'diary';

    public const LISTS = 'list';

    private const LISTS_PER_PAGE = 18;

    private const DIARY_PER_PAGE = 30;

    public User $user;

    #[Url(as: 'daftar', except: 'watched')]
    public string $tab = 'watched';

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->tab = $this->normalizedTab();
    }

    public function render(FriendshipService $friendships, ProfileStatsService $stats, FavoriteShareImageService $images)
    {
        $viewer = Auth::user();
        $canViewLibrary = $viewer->can('viewLibrary', $this->user);
        $tab = $this->normalizedTab();
        $isDiary = $tab === self::DIARY;
        $isLists = $tab === self::LISTS;

        $entries = match (true) {
            $isLists => $this->visibleLists()->withCardData()->latest('updated_at')->paginate(self::LISTS_PER_PAGE),
            $isDiary => $this->diaryEntries(),
            default => $this->entries(),
        };

        return view('livewire.user-profile', [
            'state' => $friendships->stateBetween($viewer, $this->user),
            'canViewLibrary' => $canViewLibrary,
            'favorites' => $this->favorites(),
            // Sidik isi gambar favorit, untuk tombol Bagikan milik pemilik profil.
            'shareVersion' => $this->isOwnProfile ? $images->fingerprint($this->user) : null,
            'tabs' => $this->tabs(),
            'isDiary' => $isDiary,
            'isLists' => $isLists,
            'entries' => $entries,
            'reviewedMediaIds' => $canViewLibrary && $isDiary ? $this->reviewedMediaIds($entries) : [],
            'counts' => $this->counts($canViewLibrary),
            'stats' => $canViewLibrary ? $stats->for($this->user) : null,
            'friendCount' => count($friendships->friendIdsOf($this->user)),
        ])->title($this->user->name);
    }

    public function selectTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->tabs()) ? $tab : array_key_first($this->tabs());

        $this->resetPage();
    }

    /**
     * Tab yang tersedia untuk pengunjung. Riwayat hanya untuk teman; List selalu
     * ada karena visibilitasnya diatur per list.
     *
     * @return array<string, string>
     */
    private function tabs(): array
    {
        $tabs = [];

        if (Auth::user()->can('viewLibrary', $this->user)) {
            foreach (WatchStatus::cases() as $case) {
                $tabs[$case->value] = $case->label();
            }

            $tabs[self::DIARY] = 'Diary';
        }

        return $tabs + [self::LISTS => 'List'];
    }

    public function sendFriendRequest(FriendshipService $friendships): void
    {
        try {
            $friendships->sendRequest(Auth::user(), $this->user);
        } catch (ValidationException $e) {
            $this->addError('friendship', $e->getMessage());
        }
    }

    public function acceptFriendRequest(FriendshipService $friendships): void
    {
        $friendship = $friendships->between(Auth::user(), $this->user);

        if ($friendship) {
            $friendships->accept(Auth::user(), $friendship);

            // Riwayat baru saja terbuka: tampilkan, jangan tertahan di tab List.
            $this->tab = array_key_first($this->tabs());
            $this->resetPage();
        }
    }

    public function rejectFriendRequest(FriendshipService $friendships): void
    {
        $friendship = $friendships->between(Auth::user(), $this->user);

        if ($friendship) {
            $friendships->reject(Auth::user(), $friendship);
        }
    }

    public function cancelFriendRequest(FriendshipService $friendships): void
    {
        $friendship = $friendships->between(Auth::user(), $this->user);

        if ($friendship) {
            $friendships->cancel(Auth::user(), $friendship);
        }
    }

    public function removeFriend(FriendshipService $friendships): void
    {
        $friendships->remove(Auth::user(), $this->user);
    }

    /**
     * Favorit tetap terlihat walau belum berteman — ini etalase selera, bukan
     * riwayat tontonan.
     */
    private function favorites()
    {
        return Favorite::with('media')
            ->where('user_id', $this->user->id)
            ->orderBy('sort_order')
            ->take(Favorite::MAX_PER_USER)
            ->get();
    }

    /**
     * List milik pengguna ini yang boleh dilihat pengunjung (visibilitas per list).
     */
    private function visibleLists()
    {
        return MediaList::query()->where('user_id', $this->user->id)->visibleTo(Auth::user());
    }

    private function entries()
    {
        return $this->user->watchEntries()
            ->with('media')
            ->where('status', $this->normalizedTab())
            ->latest('updated_at')
            ->paginate(18);
    }

    /**
     * Diary: judul yang sudah ditonton, urut kronologis dari yang terbaru.
     */
    private function diaryEntries()
    {
        return $this->user->watchEntries()
            ->with('media')
            ->watched()
            ->whereNotNull('watched_at')
            ->orderByDesc('watched_at')
            ->orderByDesc('id')
            ->paginate(self::DIARY_PER_PAGE);
    }

    /**
     * Judul mana saja di halaman diary ini yang juga diberi review.
     *
     * @return list<int>
     */
    private function reviewedMediaIds($entries): array
    {
        return Review::where('user_id', $this->user->id)
            ->whereIn('media_cache_id', collect($entries->items())->pluck('media_cache_id'))
            ->pluck('media_cache_id')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function counts(bool $canViewLibrary): array
    {
        // Jumlah list untuk semua pengunjung; angka riwayat hanya untuk teman.
        $counts = [self::LISTS => $this->visibleLists()->count()];

        if ($canViewLibrary) {
            $counts[WatchStatus::Watched->value] = $this->user->watchEntries()->watched()->count();
            $counts[WatchStatus::Watchlist->value] = $this->user->watchEntries()->watchlist()->count();
        }

        return $counts;
    }

    private function normalizedTab(): string
    {
        return array_key_exists($this->tab, $this->tabs()) ? $this->tab : array_key_first($this->tabs());
    }

    public function getIsOwnProfileProperty(): bool
    {
        return Auth::id() === $this->user->id;
    }
}
