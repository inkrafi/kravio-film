@props(['media'])

<article
    {{ $attributes->merge(['class' => 'group relative flex flex-col overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 transition hover:shadow-md dark:bg-gray-800 dark:ring-gray-700']) }}
>
    <div class="relative aspect-[2/3] w-full overflow-hidden bg-gray-100 dark:bg-gray-900">
        @if ($media->poster_url)
            <img
                src="{{ $media->poster_url }}"
                alt="Poster {{ $media->title }}"
                loading="lazy"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
            >
        @else
            <div class="flex h-full w-full items-center justify-center px-3 text-center text-xs text-gray-400 dark:text-gray-500">
                Poster tidak tersedia
            </div>
        @endif

        <span class="absolute left-2 top-2 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset backdrop-blur {{ $media->media_type->badgeClasses() }}">
            {{ $media->media_type->label() }}
        </span>
    </div>

    <div class="flex flex-1 flex-col gap-1 p-3">
        <h3 class="line-clamp-2 text-sm font-semibold text-gray-900 dark:text-gray-100" title="{{ $media->title }}">
            {{ $media->title }}
        </h3>

        @if ($media->title_latin)
            <p class="line-clamp-1 text-xs text-gray-600 dark:text-gray-300" title="{{ $media->title_latin }}">{{ $media->title_latin }}</p>
        @endif

        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ $media->year ?? 'Tahun tidak diketahui' }}
            @if ($media->displayGenres())
                · {{ implode(', ', array_slice($media->displayGenres(), 0, 2)) }}
            @endif
        </p>
    </div>
</article>
