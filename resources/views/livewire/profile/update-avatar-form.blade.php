<?php

use App\Services\AvatarService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public const MAX_KB = 2048;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $photo = null;

    public function updatedPhoto(): void
    {
        $this->validatePhoto();
    }

    public function save(AvatarService $avatars): void
    {
        $this->validatePhoto();

        $avatars->store(Auth::user(), $this->photo);

        $this->reset('photo');
        $this->dispatch('avatar-updated');
    }

    public function cancel(): void
    {
        $this->reset('photo');
        $this->resetValidation();
    }

    public function remove(AvatarService $avatars): void
    {
        $avatars->remove(Auth::user());

        $this->reset('photo');
        $this->dispatch('avatar-updated');
    }

    private function validatePhoto(): void
    {
        $this->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp,gif',
                'max:'.self::MAX_KB,
                'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000',
            ],
        ], attributes: ['photo' => 'foto profil']);
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Foto Profil</h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            JPG, PNG, WebP, atau GIF, maksimal 2 MB. Foto dipotong persegi dari bagian tengah.
        </p>
    </header>

    <div class="mt-6 flex items-center gap-5">
        {{-- Pratinjau: foto yang baru dipilih, atau foto saat ini --}}
        @if ($photo && ! $errors->has('photo'))
            <span class="inline-flex h-20 w-20 shrink-0 overflow-hidden rounded-full ring-2 ring-indigo-500">
                <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau foto profil" class="h-full w-full object-cover">
            </span>
        @else
            <x-avatar :user="auth()->user()" size="h-20 w-20 text-2xl" />
        @endif

        <div class="flex flex-wrap items-center gap-3">
            @if ($photo && ! $errors->has('photo'))
                <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                    Simpan foto
                </x-primary-button>
                <button type="button" wire:click="cancel" class="text-sm text-gray-600 hover:underline dark:text-gray-400">
                    Batal
                </button>
            @else
                <label class="inline-flex cursor-pointer items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm hover:bg-gray-50 focus-within:ring-2 focus-within:ring-indigo-500 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    {{ auth()->user()->avatar_path ? 'Ganti foto' : 'Pilih foto' }}
                    <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only">
                </label>

                @if (auth()->user()->avatar_path)
                    <button type="button" wire:click="remove" wire:confirm="Hapus foto profil?"
                            class="text-sm text-red-600 hover:underline dark:text-red-400">
                        Hapus foto
                    </button>
                @endif
            @endif

            <span wire:loading wire:target="photo" class="text-sm text-gray-500 dark:text-gray-400">Mengunggah…</span>

            <x-action-message on="avatar-updated">Tersimpan.</x-action-message>
        </div>
    </div>

    <x-input-error class="mt-2" :messages="$errors->get('photo')" />
</section>
