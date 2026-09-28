<?php

namespace App\Policies;

use App\Models\ProfileComment;
use App\Models\User;

class ProfileCommentPolicy
{
    /**
     * Kolom komentar mengikuti aturan riwayat tontonan: terbuka untuk pemilik
     * profil dan temannya saja.
     */
    public function viewAny(User $viewer, User $owner): bool
    {
        return $viewer->canViewLibraryOf($owner);
    }

    /**
     * Komentar baru hanya dari teman; pemilik profil tidak menulis komentar di
     * profilnya sendiri — ia cukup membalas komentar temannya.
     */
    public function create(User $viewer, User $owner): bool
    {
        return $viewer->id !== $owner->id && $viewer->canViewLibraryOf($owner);
    }

    /**
     * Membalas: pemilik profil dan teman yang bisa melihat kolom komentar.
     */
    public function reply(User $viewer, ProfileComment $comment): bool
    {
        return $viewer->canViewLibraryOf($comment->profileUser);
    }

    /**
     * Penulis boleh menghapus komentarnya sendiri; pemilik profil boleh
     * membersihkan komentar apa pun di profilnya.
     */
    public function delete(User $viewer, ProfileComment $comment): bool
    {
        return $viewer->id === $comment->commenter_id
            || $viewer->id === $comment->profile_user_id;
    }
}
