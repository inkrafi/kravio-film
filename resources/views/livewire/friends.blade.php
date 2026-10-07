<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
    <header class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Teman</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Watched, watchlist, dan diary kamu hanya terlihat oleh teman yang sudah diterima.
        </p>
    </header>

    @error('friendship')
        <p class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">{{ $message }}</p>
    @enderror

    {{-- Permintaan masuk --}}
    <section>
        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400">
            Permintaan Masuk ({{ $incoming->count() }})
        </h2>

        @if ($incoming->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada permintaan yang menunggu jawabanmu.</p>
        @else
            <ul class="mt-3 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                @foreach ($incoming as $request)
                    <li class="flex items-center justify-between gap-3 px-4 py-3" wire:key="in-{{ $request->id }}">
                        <a href="{{ route('profile.show', $request->requester) }}" wire:navigate class="flex items-center gap-3 min-w-0">
                            <x-avatar :user="$request->requester" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $request->requester->name }}</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">&#64;{{ $request->requester->username }}</span>
                            </span>
                        </a>

                        <span class="flex shrink-0 gap-2">
                            <button type="button" wire:click="accept({{ $request->id }})"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                                Terima
                            </button>
                            <button type="button" wire:click="reject({{ $request->id }})"
                                    class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                                Tolak
                            </button>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Permintaan terkirim --}}
    @if ($outgoing->isNotEmpty())
        <section class="mt-8">
            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                Menunggu Konfirmasi ({{ $outgoing->count() }})
            </h2>

            <ul class="mt-3 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                @foreach ($outgoing as $request)
                    <li class="flex items-center justify-between gap-3 px-4 py-3" wire:key="out-{{ $request->id }}">
                        <a href="{{ route('profile.show', $request->addressee) }}" wire:navigate class="flex items-center gap-3 min-w-0">
                            <x-avatar :user="$request->addressee" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $request->addressee->name }}</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">&#64;{{ $request->addressee->username }}</span>
                            </span>
                        </a>

                        <button type="button" wire:click="cancel({{ $request->id }})"
                                class="shrink-0 rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                            Batalkan
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Daftar teman --}}
    <section class="mt-8">
        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400">
            Teman Saya ({{ $friends->count() }})
        </h2>

        @if ($friends->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum punya teman. Cari lewat kolom di bawah.</p>
        @else
            <ul class="mt-3 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                @foreach ($friends as $friend)
                    <li class="flex items-center justify-between gap-3 px-4 py-3" wire:key="friend-{{ $friend->id }}">
                        <a href="{{ route('profile.show', $friend) }}" wire:navigate class="flex items-center gap-3 min-w-0">
                            <x-avatar :user="$friend" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $friend->name }}</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">&#64;{{ $friend->username }}</span>
                            </span>
                        </a>

                        <button type="button" wire:click="remove({{ $friend->id }})"
                                class="shrink-0 rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                            Putuskan
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Cari teman baru --}}
    <section class="mt-8">
        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400">Cari Teman</h2>

        <input
            type="search"
            wire:model.live.debounce.400ms="query"
            placeholder="Cari username atau nama…"
            aria-label="Cari pengguna"
            class="mt-3 block w-full rounded-lg border-gray-300 bg-white py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-gray-400 focus:ring-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
        >

        @if (trim($query) !== '')
            @if ($matches->isEmpty())
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Tidak ada pengguna baru yang cocok dengan &ldquo;{{ $query }}&rdquo;.
                </p>
            @else
                <ul class="mt-3 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    @foreach ($matches as $candidate)
                        <li class="flex items-center justify-between gap-3 px-4 py-3" wire:key="match-{{ $candidate->id }}">
                            <a href="{{ route('profile.show', $candidate) }}" wire:navigate class="flex items-center gap-3 min-w-0">
                                <x-avatar :user="$candidate" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $candidate->name }}</span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">&#64;{{ $candidate->username }}</span>
                                </span>
                            </a>

                            <button type="button" wire:click="add({{ $candidate->id }})"
                                    class="shrink-0 rounded-lg bg-perak px-3 py-1.5 text-sm font-medium text-layar hover:bg-white">
                                Tambah
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>
</div>
