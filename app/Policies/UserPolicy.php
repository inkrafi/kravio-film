<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Watched, watchlist, dan diary hanya terbuka untuk diri sendiri dan teman
     * yang permintaannya sudah diterima.
     */
    public function viewLibrary(User $viewer, User $owner): bool
    {
        return $viewer->canViewLibraryOf($owner);
    }
}
