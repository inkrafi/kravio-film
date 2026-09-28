<?php

namespace App\Livewire;

use App\Enums\MediaType;
use App\Services\Media\GenreBrowseService;
use App\Support\GenreNormalizer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman /genre/{slug}: film & series (termasuk anime) populer dalam satu genre.
 */
#[Layout('layouts.app')]
class GenreBrowse extends Component
{
    public const MAX_PAGES = 10;

    /** @var array{name: string, slug: string, tmdb_movie: ?int, tmdb_tv: ?int, anilist: ?string} */
    #[Locked]
    public array $genre;

    #[Url(as: 'tipe', except: 'film')]
    public string $type = 'film';

    #[Locked]
    public int $pages = 1;

    public function mount(string $slug): void
    {
        $this->genre = GenreNormalizer::findBySlug($slug) ?? abort(404);

        $this->type = $this->normalizedType()->value;
    }

    public function selectType(string $type): void
    {
        $this->type = MediaType::tryFrom($type)?->value ?? MediaType::Film->value;
        $this->pages = 1;
    }

    public function loadMore(): void
    {
        $this->pages = min($this->pages + 1, self::MAX_PAGES);
    }

    public function render(GenreBrowseService $browse)
    {
        $type = $this->normalizedType();
        $media = collect();
        $hasMore = false;
        $failed = [];

        for ($page = 1; $page <= $this->pages; $page++) {
            $result = $browse->page($this->genre, $type, $page);

            $media = $media->concat($result['media']);
            $hasMore = $result['has_more'];
            $failed = [...$failed, ...$result['failed']];
        }

        return view('livewire.genre-browse', [
            'media' => $media->unique('id')->values(),
            'hasMore' => $hasMore && $this->pages < self::MAX_PAGES,
            'failed' => array_values(array_unique($failed)),
            'available' => $this->availableTypes(),
        ])->title('Genre '.$this->genre['name']);
    }

    /**
     * Genre yang hanya ada di satu tipe (mis. Talk Show hanya series) tidak
     * menampilkan tab kosong.
     *
     * @return list<MediaType>
     */
    private function availableTypes(): array
    {
        return array_values(array_filter(MediaType::cases(), fn (MediaType $type) => match ($type) {
            MediaType::Film => $this->genre['tmdb_movie'] !== null || $this->genre['anilist'] !== null,
            MediaType::Series => $this->genre['tmdb_tv'] !== null || $this->genre['anilist'] !== null,
        }));
    }

    private function normalizedType(): MediaType
    {
        $available = $this->availableTypes();
        $type = MediaType::tryFrom($this->type);

        return in_array($type, $available, true) ? $type : $available[0];
    }
}
