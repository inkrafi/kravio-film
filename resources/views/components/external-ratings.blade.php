@props(['media'])

{{-- Rating TMDB, IMDb & Rotten Tomatoes untuk halaman detail; kosong kalau ketiganya belum ada. --}}
@if ($media->hasExternalRatings())
    @php
        $votes = fn (?int $count) => $count ? number_format($count, 0, ',', '.').' suara' : null;
        // Ambang "fresh" Rotten Tomatoes: 60% ke atas.
        $fresh = $media->rotten_tomatoes_score !== null && $media->rotten_tomatoes_score >= 60;
    @endphp

    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
        @if ($media->tmdb_rating !== null)
            <span class="inline-flex items-center gap-1.5 rounded-md bg-[#0d253f] px-2.5 py-1 text-sm font-semibold text-white"
                  title="Rating pengguna TMDB">
                <span class="font-black tracking-tight text-[#01b4e4]">TMDB</span>
                {{ number_format($media->tmdb_rating, 1) }}
                @if ($votes($media->tmdb_votes))
                    <span class="font-normal text-gray-300">({{ $votes($media->tmdb_votes) }})</span>
                @endif
            </span>
        @endif

        @if ($media->imdb_rating !== null)
            <span class="inline-flex items-center gap-1.5 rounded-md bg-yellow-400 px-2.5 py-1 text-sm font-semibold text-gray-900"
                  title="Rating IMDb">
                <span class="font-black tracking-tight">IMDb</span>
                {{ number_format($media->imdb_rating, 1) }}
                @if ($votes($media->imdb_votes))
                    <span class="font-normal text-gray-700">({{ $votes($media->imdb_votes) }})</span>
                @endif
            </span>
        @endif

        @if ($media->rotten_tomatoes_score !== null)
            <span @class([
                    'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-sm font-semibold text-white',
                    'bg-red-600' => $fresh,
                    'bg-lime-600' => ! $fresh,
                ])
                  title="Tomatometer Rotten Tomatoes ({{ $fresh ? 'fresh' : 'rotten' }})">
                <span aria-hidden="true">{{ $fresh ? '🍅' : '🤢' }}</span>
                <span class="sr-only">Rotten Tomatoes</span>
                {{ $media->rotten_tomatoes_score }}%
            </span>
        @endif
    </div>
@endif
