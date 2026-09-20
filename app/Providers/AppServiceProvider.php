<?php

namespace App\Providers;

use App\Contracts\MediaProvider;
use App\Services\Media\MediaSearchService;
use App\Services\Media\Providers\AniListProvider;
use App\Services\Media\Providers\JikanProvider;
use App\Services\Media\Providers\TmdbProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private const PRIMARY_MEDIA_PROVIDERS = 'media.providers.primary';

    private const FALLBACK_MEDIA_PROVIDERS = 'media.providers.fallback';

    /**
     * Sumber media utama, ditembak paralel di setiap pencarian.
     *
     * AniList dipilih sebagai sumber anime utama karena Jikan/MyAnimeList
     * kerap membalas 504.
     *
     * @var list<class-string<MediaProvider>>
     */
    private const PRIMARY = [
        TmdbProvider::class,
        AniListProvider::class,
    ];

    /**
     * Sumber cadangan, hanya dipakai kalau sumber utama untuk media type yang
     * sama sedang gagal — mis. Jikan menggantikan AniList saat AniList mati.
     *
     * @var list<class-string<MediaProvider>>
     */
    private const FALLBACK = [
        JikanProvider::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach ([...self::PRIMARY, ...self::FALLBACK] as $provider) {
            $this->app->singleton($provider);
        }

        $this->app->tag(self::PRIMARY, self::PRIMARY_MEDIA_PROVIDERS);
        $this->app->tag(self::FALLBACK, self::FALLBACK_MEDIA_PROVIDERS);

        $this->app->singleton(MediaSearchService::class, fn ($app) => new MediaSearchService(
            collect($app->tagged(self::PRIMARY_MEDIA_PROVIDERS))->values(),
            collect($app->tagged(self::FALLBACK_MEDIA_PROVIDERS))->values(),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
