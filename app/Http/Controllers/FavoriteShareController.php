<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FavoriteShareImageService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Favorit yang dibagikan: halaman publik dan gambarnya. Sengaja bisa dibuka
 * tanpa login supaya tautannya bisa dibagikan; yang terbuka hanya favorit,
 * yang memang selalu terlihat di profil, bukan watched atau diary.
 */
class FavoriteShareController extends Controller
{
    public function show(User $user, FavoriteShareImageService $images): View
    {
        $media = $images->media($user);

        abort_if($media->isEmpty(), 404);

        return view('favorites.share', [
            'owner' => $user,
            'media' => $media,
            'version' => $images->fingerprint($user),
        ]);
    }

    public function image(Request $request, User $user, string $format, FavoriteShareImageService $images): BinaryFileResponse
    {
        abort_if($images->media($user)->isEmpty() || ! isset(FavoriteShareImageService::FORMATS[$format]), 404);

        $path = $images->path($user, $format);

        if ($request->boolean('unduh')) {
            return response()->download($path, "favorit-{$user->username}-{$format}.png", ['Content-Type' => 'image/png']);
        }

        return response()->file($path, [
            'Content-Type' => 'image/png',
            // Sidik isi ada di nama file; lima menit cukup supaya perubahan cepat terlihat.
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
