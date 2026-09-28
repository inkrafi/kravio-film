@use('App\Enums\ListVisibility')
@use('App\Models\MediaListComment')

<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
    <nav aria-label="Breadcrumb">
        <ol class="flex min-w-0 items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400">
            <li class="shrink-0"><a href="{{ route('profile.show', $list->user) }}" wire:navigate class="hover:text-gray-800 dark:hover:text-gray-200">{{ $list->user->name }}</a></li>
            <li class="shrink-0 text-gray-300 dark:text-gray-600" aria-hidden="true">›</li>
            <li class="shrink-0"><a href="{{ route('lists.index', $list->user) }}" wire:navigate class="hover:text-gray-800 dark:hover:text-gray-200">List</a></li>
            <li class="shrink-0 text-gray-300 dark:text-gray-600" aria-hidden="true">›</li>
            <li class="min-w-0"><span aria-current="page" class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $list->title }}</span></li>
        </ol>
    </nav>

    <header class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $list->title }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('profile.show', $list->user) }}" wire:navigate class="inline-flex items-center gap-1.5 hover:underline">
                    <x-avatar :user="$list->user" size="h-5 w-5 text-[10px]" /> {{ $list->user->name }}
                </a>
                <span>· {{ $list->items->count() }} judul</span>
                <span>· diperbarui {{ $list->updated_at->locale('id')->diffForHumans() }}</span>
                @if ($list->visibility !== ListVisibility::Public)
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                          title="{{ $list->visibility->description() }}">{{ $list->visibility->label() }}</span>
                @endif
            </p>
            @if ($copiedFrom)
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    Disalin dari <a href="{{ $copiedFrom->url() }}" wire:navigate class="underline-offset-2 hover:underline">{{ $copiedFrom->title }}</a> oleh {{ $copiedFrom->user->name }}
                </p>
            @endif
            @if ($list->description)
                <p class="mt-3 max-w-prose whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $list->description }}</p>
            @endif
        </div>

        {{-- Aksi --}}
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            @can('update', $list)
                <a href="{{ route('lists.edit', ['list' => $list->id]) }}" wire:navigate
                   class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Ubah list</a>
                <button type="button" wire:click="deleteList" wire:confirm="Hapus list “{{ $list->title }}”? Tindakan ini tidak bisa dibatalkan."
                        class="rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">Hapus</button>
                @if ($list->likes_count)
                    <span class="text-sm text-gray-500 dark:text-gray-400">♥ {{ $list->likes_count }} suka</span>
                @endif
            @else
                @can('like', $list)
                    <button type="button" wire:click="toggleLike" aria-pressed="{{ $liked ? 'true' : 'false' }}"
                            @class([
                                'rounded-lg px-4 py-2 text-sm font-medium transition',
                                'bg-rose-600 text-white hover:bg-rose-700' => $liked,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => ! $liked,
                            ])>
                        {{ $liked ? '♥ Disukai' : '♡ Suka' }} · {{ $list->likes_count }}
                    </button>
                @endcan
                @can('copy', $list)
                    <button type="button" wire:click="copy"
                            class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        Salin ke list saya
                    </button>
                @endcan
            @endcan
        </div>
    </header>

    @error('copy')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    {{-- Isi list --}}
    @if ($list->items->isEmpty())
        <p class="mt-8 rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            List ini masih kosong.
        </p>
    @else
        <ol class="mt-8 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            @foreach ($list->items as $item)
                <li wire:key="item-{{ $item->id }}">
                    <a href="{{ $item->media->url() }}" wire:navigate class="relative block">
                        <x-media-card :media="$item->media" />
                        @if ($list->is_ranked)
                            <span class="absolute right-2 top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-gray-900/80 px-1.5 text-xs font-bold text-white shadow">{{ $loop->iteration }}</span>
                        @endif
                    </a>
                    @if ($item->note)
                        <p class="mt-1.5 whitespace-pre-line text-xs leading-snug text-gray-600 dark:text-gray-400">{{ $item->note }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    {{-- Komentar --}}
    <section class="mt-12" aria-labelledby="list-comments-heading">
        <h2 id="list-comments-heading" class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Komentar @if ($comments->isNotEmpty()) <span class="font-normal normal-case">({{ $comments->count() }})</span> @endif
        </h2>

        <form wire:submit="postComment" class="mt-3">
            <label for="list-comment" class="sr-only">Tulis komentar</label>
            <textarea id="list-comment" wire:model="commentBody" rows="2" maxlength="{{ MediaListComment::MAX_LENGTH }}"
                      placeholder="Tulis komentar tentang list ini…"
                      class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"></textarea>
            @error('commentBody')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            <div class="mt-2 flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="postComment"
                        class="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">Kirim</button>
            </div>
        </form>

        @if ($comments->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada komentar.</p>
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($comments as $comment)
                    <li wire:key="list-comment-{{ $comment->id }}" class="flex gap-3">
                        <a href="{{ route('profile.show', $comment->user) }}" wire:navigate class="shrink-0"><x-avatar :user="$comment->user" /></a>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline gap-x-2 text-sm">
                                <a href="{{ route('profile.show', $comment->user) }}" wire:navigate class="font-medium text-gray-900 hover:underline dark:text-gray-100">{{ $comment->user->name }}</a>
                                @if ($comment->user_id === $list->user_id)
                                    <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">Pembuat list</span>
                                @endif
                                <time datetime="{{ $comment->created_at->toIso8601String() }}" class="text-xs text-gray-400 dark:text-gray-500">{{ $comment->created_at->locale('id')->diffForHumans() }}</time>
                                @can('deleteComment', [$list, $comment])
                                    <button type="button" wire:click="deleteComment({{ $comment->id }})" wire:confirm="Hapus komentar ini?"
                                            class="text-xs text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400">Hapus</button>
                                @endcan
                            </div>
                            <p class="mt-0.5 whitespace-pre-line break-words text-sm text-gray-700 dark:text-gray-300">{{ $comment->body }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
