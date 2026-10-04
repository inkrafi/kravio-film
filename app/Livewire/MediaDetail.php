<?php

namespace App\Livewire;

use App\Enums\WatchStatus;
use App\Livewire\Forms\ReviewForm;
use App\Models\Favorite;
use App\Models\MediaCache;
use App\Models\Review;
use App\Models\WatchEntry;
use App\Services\FriendshipService;
use App\Services\Media\MediaDetailsService;
use App\Services\Media\MediaLocalizationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Halaman detail satu judul: info dari media_cache plus semua aksi pribadi
 * pengguna terhadap judul itu (watched/watchlist, rating, review, favorit).
 */
#[Layout('layouts.app')]
class MediaDetail extends Component
{
    public const MIN_RATING = 1;

    public const MAX_RATING = 10;

    public MediaCache $media;

    /** Nilai WatchStatus, atau null kalau judul ini belum dicatat sama sekali. */
    public ?string $status = null;

    public ?int $rating = null;

    public bool $isFavorite = false;

    public bool $editingReview = false;

    public ReviewForm $form;

    /** True setelah wire:init mencoba mengambil rating, pemain, dan sutradara. */
    public bool $detailsChecked = false;

    public function mount(MediaCache $media): void
    {
        $this->media = $media;

        $this->syncFromDatabase();
    }

    public function render(FriendshipService $friendships)
    {
        $friendEntries = $this->friendEntries($friendships->friendIdsOf(Auth::user()));

        return view('livewire.media-detail', [
            'review' => $this->currentReview(),
            'community' => $this->communityRating(),
            'friendEntries' => $friendEntries,
            'friendReviews' => $this->friendReviews($friendEntries),
            'favoriteCount' => Favorite::where('user_id', Auth::id())->count(),
            'maxFavorites' => Favorite::MAX_PER_USER,
            'detailsPending' => ! $this->detailsChecked && $this->detailsNeedRefresh(),
            'translationPending' => ! $this->detailsChecked
                && filled($this->media->synopsis)
                && app(MediaLocalizationService::class)->needsLocalization($this->media),
        ])->title($this->media->title);
    }

    /**
     * Dipanggil lewat wire:init setelah halaman tampil, supaya request ke
     * TMDB/OMDb tidak menahan render pertama.
     */
    public function loadDetails(MediaDetailsService $details, MediaLocalizationService $localization): void
    {
        $this->media = $details->refresh($this->media);
        // Setelah credits ada, supaya nama pemain & sutradara ikut dilatinkan.
        $this->media = $localization->localize($this->media);
        $this->detailsChecked = true;
    }

    /**
     * Rata-rata rating semua pengguna Kravio. Hanya angka gabungan, jadi tidak
     * membuka pustaka siapa pun yang bukan teman.
     *
     * @return array{average: ?float, ratings: int, watched: int}
     */
    private function communityRating(): array
    {
        $watched = WatchEntry::query()->where('media_cache_id', $this->media->id)->watched();

        return [
            'average' => ($average = (clone $watched)->avg('rating')) !== null ? round((float) $average, 1) : null,
            'ratings' => (clone $watched)->whereNotNull('rating')->count(),
            'watched' => $watched->count(),
        ];
    }

    /**
     * Catatan teman untuk judul ini: yang sudah menonton dulu, lalu yang
     * baru menyimpannya di watchlist.
     *
     * @param  list<int>  $friendIds
     * @return Collection<int, WatchEntry>
     */
    private function friendEntries(array $friendIds): Collection
    {
        if ($friendIds === []) {
            return collect();
        }

        return WatchEntry::query()
            ->where('media_cache_id', $this->media->id)
            ->whereIn('user_id', $friendIds)
            ->with('user')
            ->get()
            ->sortBy([
                fn (WatchEntry $a, WatchEntry $b) => ($a->status === WatchStatus::Watched ? 0 : 1) <=> ($b->status === WatchStatus::Watched ? 0 : 1),
                fn (WatchEntry $a, WatchEntry $b) => $b->watched_at?->timestamp <=> $a->watched_at?->timestamp,
            ])
            ->values();
    }

    /**
     * Review teman yang sudah menonton, dikunci per user_id.
     *
     * @param  Collection<int, WatchEntry>  $friendEntries
     * @return Collection<int, Review>
     */
    private function friendReviews(Collection $friendEntries): Collection
    {
        if ($friendEntries->isEmpty()) {
            return collect();
        }

        return Review::query()
            ->where('media_cache_id', $this->media->id)
            ->whereIn('user_id', $friendEntries->pluck('user_id'))
            ->get()
            ->keyBy('user_id');
    }

    private function detailsNeedRefresh(): bool
    {
        $details = app(MediaDetailsService::class);

        return ($details->isConfigured() && $details->isStale($this->media))
            || app(MediaLocalizationService::class)->needsLocalization($this->media);
    }

    /**
     * Pindahkan judul ini ke watched atau watchlist.
     */
    public function setStatus(string $status): void
    {
        $status = WatchStatus::tryFrom($status);

        if (! $status) {
            return;
        }

        $entry = $this->entry();

        // Tanggal tonton dicatat sekali saja; menandai ulang tidak menggesernya.
        $watchedAt = $status === WatchStatus::Watched
            ? ($entry?->watched_at ?? now())
            : null;

        WatchEntry::updateOrCreate(
            ['user_id' => Auth::id(), 'media_cache_id' => $this->media->id],
            ['status' => $status, 'watched_at' => $watchedAt],
        );

        $this->syncFromDatabase();
    }

    /**
     * Hapus catatan judul ini. Rating ikut hilang karena menempel di catatan
     * yang sama; review sengaja dibiarkan supaya tulisan tidak lenyap diam-diam.
     */
    public function removeEntry(): void
    {
        $this->entry()?->delete();

        $this->syncFromDatabase();
    }

    public function rate(int $value): void
    {
        if ($value < self::MIN_RATING || $value > self::MAX_RATING) {
            return;
        }

        $this->ensureLogged()->update(['rating' => $value]);

        $this->syncFromDatabase();
    }

    public function clearRating(): void
    {
        $this->entry()?->update(['rating' => null]);

        $this->syncFromDatabase();
    }

    public function editReview(): void
    {
        $review = $this->currentReview();

        $this->form->body = $review?->body ?? '';
        $this->form->contains_spoiler = (bool) $review?->contains_spoiler;
        $this->editingReview = true;
    }

    public function cancelReview(): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->editingReview = false;
    }

    public function saveReview(): void
    {
        $this->form->validate();

        // Menulis review berarti sudah menonton, jadi catatannya dibuat sekalian.
        $this->ensureLogged();

        Review::updateOrCreate(
            ['user_id' => Auth::id(), 'media_cache_id' => $this->media->id],
            ['body' => $this->form->body, 'contains_spoiler' => $this->form->contains_spoiler],
        );

        $this->editingReview = false;
        $this->syncFromDatabase();

        $this->dispatch('review-saved');
    }

    public function deleteReview(): void
    {
        $this->currentReview()?->delete();

        $this->form->reset();
        $this->editingReview = false;
    }

    public function toggleFavorite(): void
    {
        $favorite = Favorite::where('user_id', Auth::id())
            ->where('media_cache_id', $this->media->id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            $this->syncFromDatabase();

            return;
        }

        if (Favorite::where('user_id', Auth::id())->count() >= Favorite::MAX_PER_USER) {
            $this->addError('favorite', 'Favorit maksimal '.Favorite::MAX_PER_USER.' judul. Lepas salah satu dulu di profilmu.');

            return;
        }

        Favorite::create([
            'user_id' => Auth::id(),
            'media_cache_id' => $this->media->id,
            'sort_order' => Favorite::where('user_id', Auth::id())->max('sort_order') + 1,
        ]);

        $this->syncFromDatabase();
    }

    /**
     * Rating dan review sama-sama mengandaikan judulnya sudah ditonton.
     */
    private function ensureLogged(): WatchEntry
    {
        $entry = $this->entry();

        if ($entry && $entry->status === WatchStatus::Watched) {
            return $entry;
        }

        return WatchEntry::updateOrCreate(
            ['user_id' => Auth::id(), 'media_cache_id' => $this->media->id],
            ['status' => WatchStatus::Watched, 'watched_at' => $entry?->watched_at ?? now()],
        );
    }

    private function entry(): ?WatchEntry
    {
        return WatchEntry::where('user_id', Auth::id())
            ->where('media_cache_id', $this->media->id)
            ->first();
    }

    private function currentReview(): ?Review
    {
        return Review::where('user_id', Auth::id())
            ->where('media_cache_id', $this->media->id)
            ->first();
    }

    private function syncFromDatabase(): void
    {
        $entry = $this->entry();

        $this->status = $entry?->status->value;
        $this->rating = $entry?->rating;
        $this->isFavorite = Favorite::where('user_id', Auth::id())
            ->where('media_cache_id', $this->media->id)
            ->exists();

        $this->resetErrorBag('favorite');
    }
}
