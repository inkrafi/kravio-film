<?php

namespace App\Providers;

use App\Contracts\MediaProvider;
use App\Models\MediaCache;
use App\Services\Media\MediaSearchService;
use App\Services\Media\Providers\AniListProvider;
use App\Services\Media\Providers\JikanProvider;
use App\Services\Media\Providers\TmdbProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Sumber media utama, ditembak paralel di setiap pencarian.
     *
     * AniList dipilih sebagai sumber anime utama karena Jikan/MyAnimeList
     * kerap membalas 504. Hasil anime ikut masuk Film (movie) atau Series.
     *
     * @var list<class-string<MediaProvider>>
     */
    private const PRIMARY = [
        TmdbProvider::class,
        AniListProvider::class,
    ];

    /**
     * Sumber utama => cadangannya, hanya dipakai kalau sumber utama itu sedang
     * gagal — mis. Jikan menggantikan AniList saat AniList mati.
     *
     * @var array<class-string<MediaProvider>, class-string<MediaProvider>>
     */
    private const FALLBACK = [
        AniListProvider::class => JikanProvider::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach ([...self::PRIMARY, ...array_values(self::FALLBACK)] as $provider) {
            $this->app->singleton($provider);
        }

        $this->app->singleton(MediaSearchService::class, fn ($app) => new MediaSearchService(
            collect(self::PRIMARY)->map(fn (string $provider) => $app->make($provider)),
            collect(self::FALLBACK)->mapWithKeys(fn (string $backup, string $primary) => [
                $app->make($primary)->key() => $app->make($backup),
            ]),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Kuota gratis Gemini dibatasi per menit; job insight menunggu gilirannya.
        RateLimiter::for('gemini', fn () => Limit::perMinute(8));

        // Slug media hanya unik per tipe, jadi {media} dicari bersama {type} dari
        // $route yang sedang di-bind — bukan request() global: Livewire menjalankan
        // ulang binding ini di setiap request /livewire/update (wire:init, klik
        // tombol) di atas request palsu berisi URL halaman asal.
        Route::bind('media', fn (string $slug, $route) => MediaCache::findBySlug($route->parameter('type'), $slug)
            ?? throw (new ModelNotFoundException)->setModel(MediaCache::class, [$slug]));

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
