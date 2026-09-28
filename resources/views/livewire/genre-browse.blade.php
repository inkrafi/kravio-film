<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <nav aria-label="Breadcrumb">
        <ol class="flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
            <li><a href="{{ route('search') }}" wire:navigate class="hover:text-gray-800 dark:hover:text-gray-200">Cari</a></li>
            <li class="text-gray-300 dark:text-gray-600" aria-hidden="true">›</li>
            <li>Genre</li>
            <li class="text-gray-300 dark:text-gray-600" aria-hidden="true">›</li>
            <li><span aria-current="page" class="font-medium text-gray-900 dark:text-gray-100">{{ $genre['name'] }}</span></li>
        </ol>
    </nav>

    <header class="mt-4">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $genre['name'] }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Judul populer bergenre {{ $genre['name'] }}, termasuk anime.
        </p>
    </header>

    @if (count($available) > 1)
        <div class="mt-4 flex flex-wrap gap-2" role="tablist" aria-label="Filter tipe media">
            @foreach ($available as $case)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $type === $case->value ? 'true' : 'false' }}"
                    wire:click="selectType('{{ $case->value }}')"
                    @class([
                        'rounded-full px-4 py-1.5 text-sm font-medium transition',
                        'bg-indigo-600 text-white' => $type === $case->value,
                        'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' => $type !== $case->value,
                    ])
                >
                    {{ $case->label() }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($failed)
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700/60 dark:bg-amber-900/20 dark:text-amber-200">
            Sebagian sumber sedang tidak bisa dihubungi ({{ \App\Enums\MediaSource::labels($failed) }}), jadi daftarnya mungkin belum lengkap.
        </div>
    @endif

    <div class="mt-6">
        <div wire:loading.delay wire:target="selectType" class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            @for ($i = 0; $i < 12; $i++)
                <div class="animate-pulse overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                    <div class="aspect-[2/3] w-full bg-gray-200 dark:bg-gray-700"></div>
                    <div class="space-y-2 p-3">
                        <div class="h-3 w-4/5 rounded bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove.delay wire:target="selectType">
            @if ($media->isEmpty())
                <p class="rounded-lg border border-dashed border-gray-300 px-6 py-16 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Belum ada judul untuk genre ini.
                </p>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($media as $item)
                        <a href="{{ $item->url() }}" wire:navigate wire:key="genre-{{ $item->id }}">
                            <x-media-card :media="$item" />
                        </a>
                    @endforeach
                </div>

                @if ($hasMore)
                    <div class="mt-6 text-center">
                        <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore"
                                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 disabled:opacity-60 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span wire:loading.remove wire:target="loadMore">Muat lebih banyak</span>
                            <span wire:loading wire:target="loadMore">Memuat…</span>
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
