@use('App\Models\MediaListItem')

<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $mediaList ? 'Ubah list' : 'List baru' }}</h1>
        @if ($mediaList)
            <a href="{{ $mediaList->url() }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">Lihat list →</a>
        @endif
    </header>

    {{-- Detail list --}}
    <form wire:submit="save" class="mt-6 space-y-4 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
        <div>
            <label for="list-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Judul</label>
            <input id="list-title" type="text" wire:model="title" maxlength="100" placeholder="mis. Top 10 Anime 2024"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
            @error('title') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="list-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Deskripsi <span class="font-normal text-gray-400">(opsional)</span></label>
            <textarea id="list-description" wire:model="description" rows="3" maxlength="2000"
                      class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"></textarea>
            @error('description') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">Siapa yang bisa melihat</legend>
            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                @foreach ($visibilities as $option)
                    <label @class([
                        'flex cursor-pointer flex-col rounded-md border p-3 text-sm',
                        'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' => $visibility === $option->value,
                        'border-gray-200 dark:border-gray-700' => $visibility !== $option->value,
                    ])>
                        <span class="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                            <input type="radio" wire:model.live="visibility" value="{{ $option->value }}" class="text-indigo-600 focus:ring-indigo-500">
                            {{ $option->label() }}
                        </span>
                        <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $option->description() }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" wire:model="isRanked" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900">
            Tampilkan nomor urut (list peringkat, mis. "Top 10")
        </label>

        <div class="flex items-center justify-end gap-3">
            <x-action-message on="list-saved">Tersimpan.</x-action-message>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                {{ $mediaList ? 'Simpan' : 'Buat list & tambah judul' }}
            </button>
        </div>
    </form>

    @if ($mediaList)
        {{-- Cari & tambah judul --}}
        <section class="mt-8" aria-labelledby="add-heading">
            <h2 id="add-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Tambah judul</h2>
            <input type="search" wire:model.live.debounce.400ms="query" placeholder="Cari film, series, atau anime…" aria-label="Cari judul untuk ditambahkan"
                   class="mt-2 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
            @error('query') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            <div wire:loading.delay wire:target="query" class="mt-2 text-sm text-gray-500">mencari…</div>

            @if ($results->media->isNotEmpty())
                <ul wire:loading.remove wire:target="query" class="mt-2 divide-y divide-gray-100 rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:divide-gray-700 dark:bg-gray-800 dark:ring-gray-700">
                    @foreach ($results->media as $result)
                        <li wire:key="result-{{ $result->id }}" class="flex items-center gap-3 px-3 py-2">
                            <div class="h-12 w-8 shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-gray-900">
                                @if ($result->poster_url) <img src="{{ $result->poster_url }}" alt="" loading="lazy" class="h-full w-full object-cover"> @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $result->title }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $result->media_type->label() }}@if ($result->year) · {{ $result->year }}@endif @if ($result->title_latin) · {{ $result->title_latin }}@endif
                                </p>
                            </div>
                            @if (in_array($result->id, $inList, true))
                                <span class="shrink-0 text-xs font-medium text-emerald-600 dark:text-emerald-400">✓ Di list</span>
                            @else
                                <button type="button" wire:click="addMedia({{ $result->id }})"
                                        class="shrink-0 rounded-md bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700">Tambah</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Isi list --}}
        <section class="mt-8" aria-labelledby="items-heading">
            <h2 id="items-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Isi list <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ $items->count() }} judul)</span>
            </h2>

            @if ($items->isEmpty())
                <p class="mt-2 rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Belum ada judul. Cari di atas, atau pakai tombol "Tambah ke list" di halaman detail judul mana pun.
                </p>
            @else
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Seret ⠿ untuk mengubah urutan, atau pakai tombol ↑ ↓.</p>

                {{-- Seret-lepas: resources/js/app.js (sortableList) memanggil moveTo(id, posisi). --}}
                <ol class="mt-3 space-y-2" x-data="sortableList('moveTo')">
                    @foreach ($items as $item)
                        <li wire:key="edit-item-{{ $item->id }}" data-sort-id="{{ $item->id }}"
                            class="flex gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                            <button type="button" data-sort-handle aria-label="Seret untuk memindahkan {{ $item->media->title }}" title="Seret untuk memindahkan"
                                    class="-ml-1 flex shrink-0 cursor-grab touch-none items-start px-1 pt-1 text-gray-300 hover:text-gray-500 active:cursor-grabbing dark:text-gray-600 dark:hover:text-gray-400">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <circle cx="7" cy="5" r="1.5"/><circle cx="13" cy="5" r="1.5"/>
                                    <circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/>
                                    <circle cx="7" cy="15" r="1.5"/><circle cx="13" cy="15" r="1.5"/>
                                </svg>
                            </button>
                            <span class="w-6 shrink-0 pt-1 text-right text-sm font-bold tabular-nums text-gray-400">{{ $loop->iteration }}</span>
                            <div class="h-16 w-11 shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-gray-900">
                                @if ($item->media->poster_url) <img src="{{ $item->media->poster_url }}" alt="" loading="lazy" class="h-full w-full object-cover"> @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $item->media->title }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item->media->media_type->label() }}@if ($item->media->year) · {{ $item->media->year }}@endif</p>
                                <label for="note-{{ $item->id }}" class="sr-only">Catatan untuk {{ $item->media->title }}</label>
                                <textarea id="note-{{ $item->id }}" wire:model.blur="notes.{{ $item->id }}" rows="1" maxlength="{{ MediaListItem::MAX_NOTE_LENGTH }}"
                                          placeholder="Catatan (opsional), disimpan otomatis"
                                          class="mt-1.5 block w-full rounded-md border-gray-200 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"></textarea>
                                @error('notes.'.$item->id) <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <div class="flex gap-1">
                                    <button type="button" wire:click="moveUp({{ $item->id }})" @disabled($loop->first) aria-label="Naikkan {{ $item->media->title }}"
                                            class="rounded border border-gray-200 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-30 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700">↑</button>
                                    <button type="button" wire:click="moveDown({{ $item->id }})" @disabled($loop->last) aria-label="Turunkan {{ $item->media->title }}"
                                            class="rounded border border-gray-200 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-30 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700">↓</button>
                                </div>
                                {{-- Pindah langsung ke nomor tertentu, berguna untuk list panjang --}}
                                <label class="flex items-center gap-1 text-[11px] text-gray-400">
                                    ke #
                                    <input type="number" min="1" max="{{ $items->count() }}" value="{{ $loop->iteration }}"
                                           x-on:change="$wire.moveTo({{ $item->id }}, parseInt($event.target.value) || 1)"
                                           class="w-14 rounded border-gray-200 px-1 py-0.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                </label>
                                <button type="button" wire:click="removeItem({{ $item->id }})" class="text-xs text-red-600 hover:underline dark:text-red-400">Hapus</button>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
</div>
