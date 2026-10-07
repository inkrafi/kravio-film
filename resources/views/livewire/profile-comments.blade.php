@use('App\Livewire\ProfileComments')
@use('App\Models\ProfileComment')

{{-- Polling hanya berjalan saat kolom komentar terlihat di layar. --}}
<section
    class="mt-10"
    aria-labelledby="comments-heading"
    @if ($canView) wire:poll.{{ ProfileComments::POLL_SECONDS }}s.visible @endif
>
    <h2 id="comments-heading" class="text-sm font-semibold text-gray-500 dark:text-gray-400">
        Komentar @if ($total) <span class="font-normal normal-case">({{ $total }})</span> @endif
    </h2>

    @if (! $canView)
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Komentar hanya bisa dilihat dan ditulis oleh teman {{ $user->name }}.
        </p>
    @else
        @if ($canComment)
            {{-- Form komentar baru (teman) --}}
            <form wire:submit="post" class="mt-3">
                <label for="comment-body" class="sr-only">Tulis komentar</label>
                <textarea
                    id="comment-body"
                    wire:model="body"
                    rows="2"
                    maxlength="{{ ProfileComment::MAX_LENGTH }}"
                    placeholder="Tulis komentar untuk {{ $user->name }}…"
                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                ></textarea>

                @error('body')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="mt-2 flex justify-end">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="post"
                        class="rounded-lg bg-perak px-4 py-1.5 text-sm font-medium text-layar hover:bg-white disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="post">Kirim</span>
                        <span wire:loading wire:target="post">Mengirim…</span>
                    </button>
                </div>
            </form>
        @elseif ($isOwner)
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Ini profilmu — teman-temanmu yang menulis komentar di sini, dan kamu bisa membalasnya.
            </p>
        @endif

        {{-- Daftar komentar, terbaru di atas; balasan urut kronologis di bawahnya --}}
        @if ($comments->isEmpty())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                {{ $isOwner ? 'Belum ada komentar dari teman.' : 'Belum ada komentar.' }}
            </p>
        @else
            <ul class="mt-4 space-y-5">
                @foreach ($comments as $comment)
                    <li wire:key="comment-{{ $comment->id }}">
                        @include('livewire.partials.profile-comment-item', ['comment' => $comment, 'reply' => false])

                        @if ($comment->replies->isNotEmpty() || in_array($replyingTo, [$comment->id, ...$comment->replies->pluck('id')], true))
                            <div class="ml-12 mt-3 space-y-3 border-l-2 border-gray-100 pl-4 dark:border-gray-800">
                                @foreach ($comment->replies as $reply)
                                    <div wire:key="reply-{{ $reply->id }}">
                                        @include('livewire.partials.profile-comment-item', ['comment' => $reply, 'reply' => true])
                                    </div>
                                @endforeach

                                {{-- Form balasan untuk utas ini --}}
                                @if (in_array($replyingTo, [$comment->id, ...$comment->replies->pluck('id')], true))
                                    <form wire:submit="postReply" wire:key="reply-form-{{ $comment->id }}">
                                        <label for="reply-body-{{ $comment->id }}" class="sr-only">Tulis balasan</label>
                                        <textarea
                                            id="reply-body-{{ $comment->id }}"
                                            wire:model="replyBody"
                                            rows="2"
                                            maxlength="{{ ProfileComment::MAX_LENGTH }}"
                                            placeholder="Balas {{ $comment->commenter->name }}…"
                                            x-init="$el.focus()"
                                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        ></textarea>

                                        @error('replyBody')
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror

                                        <div class="mt-2 flex justify-end gap-2">
                                            <button type="button" wire:click="cancelReply"
                                                    class="rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
                                                Batal
                                            </button>
                                            <button type="submit" wire:loading.attr="disabled" wire:target="postReply"
                                                    class="rounded-lg bg-perak px-4 py-1.5 text-sm font-medium text-layar hover:bg-white disabled:opacity-60">
                                                <span wire:loading.remove wire:target="postReply">Balas</span>
                                                <span wire:loading wire:target="postReply">Mengirim…</span>
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($hasMore)
                <button type="button" wire:click="loadMore"
                        class="mt-4 text-sm font-medium text-perak hover:underline dark:text-perak">
                    Muat komentar lebih lama
                </button>
            @endif
        @endif
    @endif
</section>
