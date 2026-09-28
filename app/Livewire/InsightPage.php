<?php

namespace App\Livewire;

use App\Jobs\GenerateUserInsight;
use App\Models\MediaCache;
use App\Models\User;
use App\Services\Insights\InsightService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Insight AI milik pengguna yang sedang login (halaman pribadi).
 *
 * Hanya membaca hasil yang tersimpan; pembuatan selalu lewat queue —
 * terjadwal mingguan atau tombol manual yang dibatasi sehari sekali.
 */
#[Layout('layouts.app')]
#[Title('Insight Mingguan')]
class InsightPage extends Component
{
    public function render(InsightService $insights)
    {
        $user = Auth::user();
        $insight = $insights->latest($user);
        $content = $insight?->content ?? [];

        $mediaIds = collect($content['recommendations'] ?? [])->pluck('media_id')
            ->concat(collect($content['friends'] ?? [])->flatMap(fn ($friend) => array_column($friend['picks'], 'media_id')));

        return view('livewire.insight-page', [
            'insight' => $insight,
            'content' => $content,
            'media' => MediaCache::query()->whereIn('id', $mediaIds->unique())->get()->keyBy('id'),
            'friendUsers' => User::query()->whereIn('id', collect($content['friends'] ?? [])->pluck('user_id'))->get()->keyBy('id'),
            'configured' => $insights->isConfigured(),
            'enoughData' => $insights->hasEnoughData($user),
            'canRefresh' => $insights->canRefreshManually($user),
            'pending' => Cache::has(GenerateUserInsight::pendingKey($user->id)),
            'failed' => Cache::get(GenerateUserInsight::failedKey($user->id)),
            'minWatched' => InsightService::MIN_WATCHED,
        ]);
    }

    public function generate(InsightService $insights): void
    {
        $user = Auth::user();

        if (! $insights->isConfigured() || ! $insights->canRefreshManually($user) || Cache::has(GenerateUserInsight::pendingKey($user->id))) {
            return;
        }

        Cache::put(GenerateUserInsight::pendingKey($user->id), true, now()->addMinutes(15));
        Cache::forget(GenerateUserInsight::failedKey($user->id));

        GenerateUserInsight::dispatch($user);
    }
}
