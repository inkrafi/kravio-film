@props(['media'])

{{-- Poster sebagai bendanya sendiri: tanpa bingkai kartu, sudut nyaris siku seperti cetakan film. --}}
<article {{ $attributes->merge(['class' => 'group flex flex-col']) }}>
    <div class="aspect-[2/3] w-full overflow-hidden rounded-sm bg-kursi">
        @if ($media->poster_url)
            <img
                src="{{ $media->poster_url }}"
                alt="Poster {{ $media->title }}"
                loading="lazy"
                class="h-full w-full object-cover"
            >
        @else
            <div class="flex h-full w-full items-end p-3">
                <span class="font-condensed text-lg font-bold leading-tight text-gray-400">{{ $media->title }}</span>
            </div>
        @endif
    </div>

    <h3 class="mt-2 line-clamp-2 text-sm font-semibold leading-snug text-gray-100 group-hover:underline group-hover:decoration-gray-500 group-hover:underline-offset-2" title="{{ $media->title }}">
        {{ $media->title }}
    </h3>

    @if ($media->title_latin)
        <p class="line-clamp-1 text-xs text-gray-400" title="{{ $media->title_latin }}">{{ $media->title_latin }}</p>
    @endif

    <p class="mt-0.5 text-xs text-gray-500">
        {{ $media->media_type->label() }}@if ($media->year), {{ $media->year }}@endif
    </p>
</article>
