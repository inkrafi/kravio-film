<?php

use App\Http\Controllers\FavoriteShareController;
use App\Http\Controllers\LandingController;
use App\Livewire\Dashboard;
use App\Livewire\Friends;
use App\Livewire\GenreBrowse;
use App\Livewire\InsightPage;
use App\Livewire\ListEditor;
use App\Livewire\ListShow;
use App\Livewire\MediaDetail;
use App\Livewire\MediaSearch;
use App\Livewire\PersonDetail;
use App\Livewire\UserProfile;
use App\Models\MediaCache;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

// Favorit yang dibagikan: bisa dibuka dan dipratinjau di WA/X tanpa login.
Route::get('u/{user}/favorit', [FavoriteShareController::class, 'show'])->name('favorites.share');
Route::get('u/{user}/favorit/{format}.png', [FavoriteShareController::class, 'image'])
    ->whereIn('format', ['kotak', 'story'])
    ->name('favorites.image');

Route::middleware('auth')->group(function () {
    Route::get('search', MediaSearch::class)->name('search');

    Route::get('teman', Friends::class)->name('friends');

    // /orang/2963-nicolas-cage — slug nama hanya pemanis, yang dipakai ID TMDB.
    Route::get('orang/{person}', PersonDetail::class)->name('person.show');

    Route::get('genre/{slug}', GenreBrowse::class)->name('genre.show');

    Route::get('insight', InsightPage::class)->name('insight');

    // List buatan pengguna. "baru" didaftarkan sebelum {list} supaya tidak dianggap id.
    Route::get('list/baru', ListEditor::class)->name('lists.create');
    Route::get('list/{list}/ubah', ListEditor::class)->name('lists.edit');
    Route::get('list/{list}', ListShow::class)->name('lists.show');
    // Daftar list seseorang ada di tab List profilnya; URL lama tetap berfungsi.
    Route::get('u/{user}/list', fn (User $user) => redirect()->route('profile.show', ['user' => $user, 'daftar' => 'list']))
        ->name('lists.index');

    Route::get('u/{user}', UserProfile::class)->name('profile.show');

    Route::view('profile', 'profile')->name('profile');

    // Beranda: aktivitas teman, watchlist, dan rekomendasi.
    Route::get('dashboard', Dashboard::class)
        ->middleware('verified')
        ->name('dashboard');

    // /film/interstellar, /series/breaking-bad — binding {media} ada di AppServiceProvider.
    Route::get('{type}/{media}', MediaDetail::class)
        ->whereIn('type', ['film', 'series'])
        ->name('media.show');

    // Tautan lama /media/tmdb-film-157336 dialihkan permanen ke URL baru.
    Route::get('media/{key}', function (string $key) {
        $media = MediaCache::findByLegacyKey($key) ?? abort(404);

        return redirect($media->url(), 301);
    })->name('media.legacy');
});

require __DIR__.'/auth.php';
