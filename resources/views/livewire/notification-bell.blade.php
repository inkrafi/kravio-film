<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" wire:poll.60s>
    <button type="button" x-on:click="open = ! open"
            class="relative inline-flex items-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-900 dark:hover:text-gray-300"
            aria-label="Notifikasi{{ $unreadCount ? " ($unreadCount belum dibaca)" : '' }}">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>

        @if ($unreadCount)
            <span class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold leading-4 text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right
         class="absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-gray-700">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Notifikasi</p>
            @if ($unreadCount)
                <button type="button" wire:click="markAllAsRead" class="text-xs text-perak hover:underline dark:text-perak">
                    Tandai semua dibaca
                </button>
            @endif
        </div>

        @if ($notifications->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada notifikasi.</p>
        @else
            <ul class="max-h-96 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-700">
                @foreach ($notifications as $notification)
                    @php $actor = $actors->get($notification->data['actor_id'] ?? null); @endphp

                    <li wire:key="notification-{{ $notification->id }}">
                        <button type="button" wire:click="open('{{ $notification->id }}')"
                                @class([
                                    'flex w-full gap-3 px-4 py-3 text-start text-sm transition hover:bg-gray-50 dark:hover:bg-gray-700/50',
                                    'bg-gray-800/60 dark:bg-gray-700/20' => $notification->unread(),
                                ])>
                            @if ($actor)
                                <x-avatar :user="$actor" size="h-8 w-8 text-xs" />
                            @endif

                            <span class="min-w-0 flex-1">
                                <span class="block text-gray-700 dark:text-gray-300">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $actor?->name ?? $notification->data['actor_name'] ?? 'Seseorang' }}</span>
                                    {{ $notification->data['message'] ?? '' }}
                                </span>
                                <span class="mt-0.5 block text-xs text-gray-400 dark:text-gray-500">{{ $notification->created_at->locale('id')->diffForHumans() }}</span>
                            </span>

                            @if ($notification->unread())
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-perak" aria-label="Belum dibaca"></span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
