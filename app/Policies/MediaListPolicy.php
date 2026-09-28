<?php

namespace App\Policies;

use App\Enums\ListVisibility;
use App\Models\MediaList;
use App\Models\MediaListComment;
use App\Models\User;

class MediaListPolicy
{
    public function view(User $viewer, MediaList $list): bool
    {
        return match ($list->visibility) {
            ListVisibility::Public => true,
            ListVisibility::Friends => $viewer->canViewLibraryOf($list->user),
            ListVisibility::Private => $viewer->id === $list->user_id,
        };
    }

    public function update(User $viewer, MediaList $list): bool
    {
        return $viewer->id === $list->user_id;
    }

    public function delete(User $viewer, MediaList $list): bool
    {
        return $viewer->id === $list->user_id;
    }

    /**
     * Menyukai dan menyalin list milik orang lain yang boleh dilihat.
     */
    public function like(User $viewer, MediaList $list): bool
    {
        return $viewer->id !== $list->user_id && $this->view($viewer, $list);
    }

    public function copy(User $viewer, MediaList $list): bool
    {
        return $viewer->id !== $list->user_id && $this->view($viewer, $list);
    }

    public function comment(User $viewer, MediaList $list): bool
    {
        return $this->view($viewer, $list);
    }

    /**
     * Penulis menghapus komentarnya sendiri; pemilik list boleh menghapus komentar apa pun di list-nya.
     */
    public function deleteComment(User $viewer, MediaList $list, MediaListComment $comment): bool
    {
        return $viewer->id === $comment->user_id || $viewer->id === $list->user_id;
    }
}
