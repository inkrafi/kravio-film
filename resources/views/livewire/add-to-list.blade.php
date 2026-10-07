<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <button type="button" x-on:click="open = ! open" :aria-expanded="open"
            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-200 transition hover:bg-gray-700">
        Tambah ke list
        @php $inCount = $lists->where('contains_media', true)->count(); @endphp
        @if ($inCount)
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">(ada di {{ $inCount }})</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition x-on:click.outside="open = false"
         class="absolute left-0 z-20 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-md bg-kursi p-3 shadow-xl shadow-black/40 ring-1 ring-gray-700">
        <p class="text-sm font-semibold text-gray-100">List kamu</p>

        @if ($lists->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum punya list. Buat yang pertama di bawah.</p>
        @else
            <ul class="mt-2 max-h-60 space-y-1 overflow-y-auto">
                @foreach ($lists as $list)
                    <li wire:key="add-list-{{ $list->id }}">
                        <label class="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <input type="checkbox" @checked($list->contains_media) wire:click="toggle({{ $list->id }})"
                                   class="rounded border-gray-300 text-perak focus:ring-gray-400 dark:border-gray-600 dark:bg-gray-900">
                            <span class="min-w-0 flex-1 truncate text-gray-800 dark:text-gray-200">{{ $list->title }}</span>
                            <span class="shrink-0 text-xs text-gray-400">{{ $list->items_count }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif

        @error('list')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        <form wire:submit="createAndAdd" class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-700">
            <label for="new-list-title" class="text-xs font-medium text-gray-500 dark:text-gray-400">List baru berisi judul ini</label>
            <div class="mt-1 flex gap-2">
                <input id="new-list-title" type="text" wire:model="newTitle" maxlength="100" placeholder="mis. Wajib ditonton"
                       class="min-w-0 flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                <button type="submit" class="shrink-0 rounded-md bg-perak px-3 text-sm font-medium text-layar hover:bg-white">Buat</button>
            </div>
            @error('newTitle')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">List baru bersifat publik; ubah di halaman list.</p>
        </form>
    </div>
</div>
