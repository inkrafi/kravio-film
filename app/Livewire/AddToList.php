<?php

namespace App\Livewire;

use App\Enums\ListVisibility;
use App\Models\MediaCache;
use App\Services\MediaListService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Tombol "Tambah ke list" di halaman detail: centang/lepas judul ini di list
 * milik sendiri, atau langsung buat list baru berisi judul ini.
 */
class AddToList extends Component
{
    #[Locked]
    public MediaCache $media;

    public string $newTitle = '';

    public function mount(MediaCache $media): void
    {
        $this->media = $media;
    }

    public function render()
    {
        $lists = Auth::user()->mediaLists()
            ->withCount('items')
            ->withExists(['items as contains_media' => fn ($items) => $items->where('media_cache_id', $this->media->id)])
            ->latest('updated_at')
            ->get();

        return view('livewire.add-to-list', ['lists' => $lists]);
    }

    public function toggle(int $listId, MediaListService $lists): void
    {
        $list = Auth::user()->mediaLists()->find($listId);

        if (! $list) {
            return;
        }

        $this->authorize('update', $list);

        if ($list->items()->where('media_cache_id', $this->media->id)->exists()) {
            $lists->remove($list, $this->media);

            return;
        }

        try {
            $lists->add($list, $this->media);
        } catch (ValidationException $e) {
            $this->addError('list', $e->getMessage());
        }
    }

    public function createAndAdd(MediaListService $lists): void
    {
        $this->validate(['newTitle' => ['required', 'string', 'max:100']], attributes: ['newTitle' => 'judul list']);

        try {
            $lists->ensureCanCreate(Auth::user());
        } catch (ValidationException $e) {
            $this->addError('newTitle', $e->getMessage());

            return;
        }

        $list = Auth::user()->mediaLists()->create([
            'title' => trim($this->newTitle),
            'visibility' => ListVisibility::Public,
        ]);

        $lists->add($list, $this->media);

        $this->reset('newTitle');
    }
}
