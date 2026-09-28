<?php

namespace App\Livewire;

use App\Enums\ListVisibility;
use App\Models\MediaCache;
use App\Models\MediaList;
use App\Models\MediaListItem;
use App\Services\Media\Dto\MediaSearchResults;
use App\Services\Media\MediaSearchService;
use App\Services\MediaListService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Membuat list baru (/list/baru) atau mengubah list sendiri (/list/{id}/ubah):
 * detail list, cari & tambah judul, urutkan, dan catatan per judul.
 */
#[Layout('layouts.app')]
class ListEditor extends Component
{
    private const SEARCH_LIMIT = 8;

    #[Locked]
    public ?MediaList $mediaList = null;

    public string $title = '';

    public string $description = '';

    public string $visibility = 'public';

    public bool $isRanked = false;

    public string $query = '';

    /** @var array<int, string> Catatan per item, dikunci dengan id item. */
    public array $notes = [];

    public function mount(?string $list = null): void
    {
        if ($list === null) {
            return;
        }

        $found = MediaList::findByRouteKey($list);

        abort_if($found === null || Auth::user()->cannot('update', $found), 404);

        $this->mediaList = $found;
        $this->title = $found->title;
        $this->description = (string) $found->description;
        $this->visibility = $found->visibility->value;
        $this->isRanked = $found->is_ranked;
        $this->notes = $found->items()->pluck('note', 'id')->map(fn ($note) => (string) $note)->all();
    }

    public function render(MediaSearchService $search)
    {
        $items = $this->mediaList?->items()->with('media')->get() ?? collect();

        $results = $this->mediaList && mb_strlen(trim($this->query)) >= 2
            ? $search->search($this->query, null, self::SEARCH_LIMIT)
            : MediaSearchResults::empty();

        return view('livewire.list-editor', [
            'items' => $items,
            'results' => $results,
            'inList' => $items->pluck('media_cache_id')->all(),
            'visibilities' => ListVisibility::cases(),
        ])->title($this->mediaList ? 'Ubah list' : 'List baru');
    }

    public function save(MediaListService $lists): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'visibility' => ['required', Rule::enum(ListVisibility::class)],
            'isRanked' => ['boolean'],
        ], attributes: ['title' => 'judul list', 'description' => 'deskripsi']);

        $attributes = [
            'title' => trim($data['title']),
            'description' => filled($data['description']) ? trim($data['description']) : null,
            'visibility' => $data['visibility'],
            'is_ranked' => $data['isRanked'],
        ];

        if ($this->mediaList) {
            $this->authorize('update', $this->mediaList);
            $this->mediaList->update($attributes);
            $this->dispatch('list-saved');

            return;
        }

        try {
            $lists->ensureCanCreate(Auth::user());
        } catch (ValidationException $e) {
            $this->addError('title', $e->getMessage());

            return;
        }

        $list = Auth::user()->mediaLists()->create($attributes);

        // Lanjut ke halaman ubah untuk mengisi judul-judulnya.
        $this->redirect(route('lists.edit', ['list' => $list->id]), navigate: true);
    }

    public function addMedia(int $mediaId, MediaListService $lists): void
    {
        $this->authorizeEdit();

        $media = MediaCache::find($mediaId);

        if (! $media) {
            return;
        }

        try {
            $item = $lists->add($this->mediaList, $media);
            $this->notes[$item->id] ??= '';
        } catch (ValidationException $e) {
            $this->addError('query', $e->getMessage());
        }
    }

    public function removeItem(int $itemId, MediaListService $lists): void
    {
        $this->authorizeEdit();

        $item = $this->mediaList->items()->find($itemId);

        if ($item) {
            $lists->remove($this->mediaList, $item->media_cache_id);
            unset($this->notes[$itemId]);
        }
    }

    public function moveTo(int $itemId, int $position, MediaListService $lists): void
    {
        $this->authorizeEdit();

        $lists->move($this->mediaList, $itemId, $position);
    }

    public function moveUp(int $itemId, MediaListService $lists): void
    {
        $this->shift($itemId, -1, $lists);
    }

    public function moveDown(int $itemId, MediaListService $lists): void
    {
        $this->shift($itemId, 1, $lists);
    }

    /**
     * Catatan tersimpan saat kolomnya ditinggalkan (wire:model.blur).
     */
    public function updatedNotes(mixed $value, string $itemId): void
    {
        $this->authorizeEdit();

        $this->validate(
            ["notes.{$itemId}" => ['nullable', 'string', 'max:'.MediaListItem::MAX_NOTE_LENGTH]],
            attributes: ["notes.{$itemId}" => 'catatan'],
        );

        app(MediaListService::class)->updateNote($this->mediaList, (int) $itemId, $value);
    }

    private function shift(int $itemId, int $delta, MediaListService $lists): void
    {
        $this->authorizeEdit();

        $item = $this->mediaList->items()->find($itemId);

        if ($item) {
            $lists->move($this->mediaList, $itemId, $item->position + $delta);
        }
    }

    private function authorizeEdit(): void
    {
        abort_if($this->mediaList === null, 404);

        $this->authorize('update', $this->mediaList);
    }
}
