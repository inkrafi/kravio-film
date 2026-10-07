@props(['media'])

{{-- Rating TMDB, IMDb & Rotten Tomatoes untuk halaman detail; kosong kalau ketiganya belum ada. --}}
@if ($media->hasExternalRatings())
    @php
        $votes = fn (?int $count) => $count ? number_format($count, 0, ',', '.').' suara' : null;
        // Ambang "fresh" Rotten Tomatoes: 60% ke atas.
        $fresh = $media->rotten_tomatoes_score !== null && $media->rotten_tomatoes_score >= 60;
    @endphp

    <dl {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
        @if ($media->tmdb_rating !== null)
            <div class="flex items-baseline gap-2" title="Rating pengguna TMDB">
                <dt class="w-24 shrink-0 text-gray-500">TMDB</dt>
                <dd class="text-gray-100">
                    <span class="font-semibold">{{ number_format($media->tmdb_rating, 1) }}</span>
                    @if ($votes($media->tmdb_votes))
                        <span class="block text-xs text-gray-500">{{ $votes($media->tmdb_votes) }}</span>
                    @endif
                </dd>
            </div>
        @endif

        @if ($media->imdb_rating !== null)
            <div class="flex items-baseline gap-2" title="Rating IMDb">
                <dt class="w-24 shrink-0 text-gray-500">IMDb</dt>
                <dd class="text-gray-100">
                    <span class="font-semibold">{{ number_format($media->imdb_rating, 1) }}</span>
                    @if ($votes($media->imdb_votes))
                        <span class="block text-xs text-gray-500">{{ $votes($media->imdb_votes) }}</span>
                    @endif
                </dd>
            </div>
        @endif

        @if ($media->rotten_tomatoes_score !== null)
            <div class="flex items-baseline gap-2" title="Tomatometer Rotten Tomatoes ({{ $fresh ? 'fresh' : 'rotten' }})">
                <dt class="w-24 shrink-0 text-gray-500">Rotten Tomatoes</dt>
                <dd class="text-gray-100">
                    <span class="font-semibold">{{ $media->rotten_tomatoes_score }}%</span>
                    <span class="text-xs text-gray-500">{{ $fresh ? 'fresh' : 'rotten' }}</span>
                </dd>
            </div>
        @endif
    </dl>
@endif
