<?php

use App\Livewire\MediaSearch;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('search', MediaSearch::class)
    ->middleware(['auth'])
    ->name('search');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
