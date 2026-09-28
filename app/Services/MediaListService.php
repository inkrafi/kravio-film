<?php

namespace App\Services;

use App\Enums\ListVisibility;
use App\Models\MediaCache;
use App\Models\MediaList;
use App\Models\MediaListItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operasi isi list: menambah, menghapus, mengurutkan, dan menyalin. Urutan
 * disimpan di kolom position yang selalu rapat (1, 2, 3, …).
 */
class MediaListService
{
    /**
     * @throws ValidationException kalau list sudah penuh.
     */
    public function add(MediaList $list, MediaCache $media, ?string $note = null): MediaListItem
    {
        return DB::transaction(function () use ($list, $media, $note) {
            $existing = $list->items()->where('media_cache_id', $media->id)->first();

            if ($existing) {
                return $existing;
            }

            if ($list->items()->count() >= MediaList::MAX_ITEMS) {
                throw ValidationException::withMessages([
                    'list' => 'List ini sudah berisi '.MediaList::MAX_ITEMS.' judul, batas maksimalnya.',
                ]);
            }

            $item = $list->items()->create([
                'media_cache_id' => $media->id,
                'position' => (int) $list->items()->max('position') + 1,
                'note' => filled($note) ? trim($note) : null,
            ]);

            $list->touch();

            return $item;
        });
    }

    public function remove(MediaList $list, MediaCache|int $media): void
    {
        DB::transaction(function () use ($list, $media) {
            $list->items()->where('media_cache_id', $media instanceof MediaCache ? $media->id : $media)->delete();
            $this->renumber($list);
            $list->touch();
        });
    }

    /**
     * Pindahkan satu judul ke posisi baru (1 = paling atas); judul lain bergeser.
     */
    public function move(MediaList $list, int $itemId, int $position): void
    {
        DB::transaction(function () use ($list, $itemId, $position) {
            $ids = $list->items()->pluck('id')->all();
            $from = array_search($itemId, $ids, true);

            if ($from === false) {
                return;
            }

            array_splice($ids, $from, 1);
            array_splice($ids, max(0, min($position - 1, count($ids))), 0, [$itemId]);

            $this->saveOrder($list, $ids);
            $list->touch();
        });
    }

    public function updateNote(MediaList $list, int $itemId, ?string $note): void
    {
        $list->items()->whereKey($itemId)->update(['note' => filled($note) ? trim($note) : null]);
        $list->touch();
    }

    /**
     * Salin list orang lain jadi list pribadi milik $user. Catatan per judul
     * tidak ikut disalin — itu tulisan pemilik aslinya.
     */
    public function copy(MediaList $source, User $user): MediaList
    {
        $this->ensureCanCreate($user);

        return DB::transaction(function () use ($source, $user) {
            $copy = $user->mediaLists()->create([
                'title' => mb_substr($source->title.' (salinan)', 0, 100),
                'description' => $source->description,
                'visibility' => ListVisibility::Private,
                'is_ranked' => $source->is_ranked,
                'copied_from_id' => $source->id,
            ]);

            $rows = $source->items()->get(['media_cache_id', 'position'])
                ->map(fn (MediaListItem $item) => [
                    'media_list_id' => $copy->id,
                    'media_cache_id' => $item->media_cache_id,
                    'position' => $item->position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            MediaListItem::insert($rows);

            return $copy;
        });
    }

    /**
     * @throws ValidationException kalau pengguna sudah punya terlalu banyak list.
     */
    public function ensureCanCreate(User $user): void
    {
        if ($user->mediaLists()->count() >= MediaList::MAX_PER_USER) {
            throw ValidationException::withMessages([
                'list' => 'Kamu sudah punya '.MediaList::MAX_PER_USER.' list, batas maksimalnya. Hapus salah satu dulu.',
            ]);
        }
    }

    private function renumber(MediaList $list): void
    {
        $this->saveOrder($list, $list->items()->pluck('id')->all());
    }

    /**
     * @param  list<int>  $ids
     */
    private function saveOrder(MediaList $list, array $ids): void
    {
        foreach (array_values($ids) as $index => $id) {
            MediaListItem::whereKey($id)->where('media_list_id', $list->id)->update(['position' => $index + 1]);
        }
    }
}
