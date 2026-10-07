<?php

namespace App\Http\Controllers;

use App\Enums\MediaSource;
use App\Models\MediaCache;
use App\Services\Media\TrendingService;
use Illuminate\View\View;

/**
 * Landing page untuk pengunjung. Hanya membaca database dan cache, tanpa
 * memanggil API luar, supaya halaman pertama yang dilihat orang selalu cepat.
 */
class LandingController extends Controller
{
    private const POPULAR_LIMIT = 6;

    public function __invoke(TrendingService $trending): View
    {
        $popular = $trending->cached()?->filter(fn (MediaCache $media) => filled($media->poster_url))->take(self::POPULAR_LIMIT)->values();

        // Adegan untuk contoh subtitle: utamakan judul yang sedang ramai.
        $still = $popular?->first(fn (MediaCache $media) => filled($media->backdrop_url))
            ?? MediaCache::query()
                ->where('source', MediaSource::Tmdb)
                ->whereNotNull('backdrop_url')
                ->latest('updated_at')
                ->first();

        return view('landing', [
            'popular' => $popular ?? collect(),
            'still' => $still,
        ]);
    }
}
