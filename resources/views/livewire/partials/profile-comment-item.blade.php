{{-- Satu komentar atau balasan. $reply = true untuk tampilan balasan yang lebih kecil. --}}
<div class="flex gap-3">
    <a href="{{ route('profile.show', $comment->commenter) }}" wire:navigate class="shrink-0">
        <x-avatar :user="$comment->commenter" :size="$reply ? 'h-7 w-7 text-xs' : 'h-9 w-9 text-sm'" />
    </a>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-x-2 text-sm">
            <a href="{{ route('profile.show', $comment->commenter) }}" wire:navigate
               class="font-medium text-gray-900 hover:underline dark:text-gray-100">
                {{ $comment->commenter->name }}
            </a>
            @if ($comment->commenter_id === $comment->profile_user_id)
                <span class="rounded bg-gray-800 px-1.5 py-0.5 text-[10px] font-semibold text-perak dark:bg-gray-700/40 dark:text-perak">Pemilik profil</span>
            @endif
            <time datetime="{{ $comment->created_at->toIso8601String() }}"
                  title="{{ $comment->created_at->locale('id')->translatedFormat('j F Y, H:i') }}"
                  class="text-xs text-gray-400 dark:text-gray-500">
                {{ $comment->created_at->locale('id')->diffForHumans() }}
            </time>

            @if ($canReply)
                <button type="button" wire:click="startReply({{ $comment->id }})"
                        class="text-xs text-gray-400 hover:text-white dark:text-gray-500 dark:hover:text-white">
                    Balas
                </button>
            @endif

            @can('delete', $comment)
                <button type="button"
                        wire:click="delete({{ $comment->id }})"
                        wire:confirm="{{ $reply ? 'Hapus balasan ini?' : 'Hapus komentar ini beserta balasannya?' }}"
                        class="text-xs text-gray-400 hover:text-red-600 dark:text-gray-500 dark:hover:text-red-400">
                    Hapus
                </button>
            @endcan
        </div>

        <p class="mt-0.5 whitespace-pre-line break-words text-sm text-gray-700 dark:text-gray-300">{{ $comment->body }}</p>
    </div>
</div>
