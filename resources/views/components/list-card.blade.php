@props(['list', 'showOwner' => true])

{{-- Kartu list: deretan poster pertama, judul, pemilik, jumlah judul & like. --}}
<a href="{{ $list->url() }}" wire:navigate
   {{ $attributes->merge(['class' => 'group block rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200 transition hover:shadow-md dark:bg-gray-800 dark:ring-gray-700']) }}>
    <div class="flex h-24 overflow-hidden rounded-md bg-gray-100 dark:bg-gray-900">
        @forelse ($list->items->take(5) as $item)
            <div class="relative h-full w-1/5 shrink-0 overflow-hidden border-r-2 border-white last:border-r-0 dark:border-gray-800">
                @if ($item->media->poster_url)
                    <img src="{{ $item->media->poster_url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                @endif
            </div>
        @empty
            <div class="flex w-full items-center justify-center text-xs text-gray-400 dark:text-gray-500">List kosong</div>
        @endforelse
    </div>

    <div class="mt-2 flex items-start justify-between gap-2">
        <h3 class="line-clamp-2 text-sm font-semibold text-gray-900 group-hover:underline dark:text-gray-100">{{ $list->title }}</h3>
        @if ($list->visibility !== \App\Enums\ListVisibility::Public)
            <span class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $list->visibility->label() }}</span>
        @endif
    </div>

    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
        @if ($showOwner){{ $list->user->name }} · @endif{{ $list->items_count }} judul
        @if ($list->likes_count) · ♥ {{ $list->likes_count }} @endif
    </p>
</a>
