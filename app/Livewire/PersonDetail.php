<?php

namespace App\Livewire;

use App\Services\Ai\TextTranslator;
use App\Services\Media\PersonService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Halaman orang: profil + judul yang pernah ia sutradarai/kreasikan dan bintangi.
 */
#[Layout('layouts.app')]
class PersonDetail extends Component
{
    #[Locked]
    public int $personId;

    #[Locked]
    public ?string $slug = null;

    public ?string $biographyId = null;

    public bool $biographyChecked = false;

    /**
     * @param  string  $person  "{id}-{slug}" atau "{id}"; slug hanya pemanis URL.
     */
    public function mount(string $person): void
    {
        abort_unless(preg_match('/^(\d+)(?:-([a-z0-9-]*))?$/', $person, $matches), 404);

        $this->personId = (int) $matches[1];
        $this->slug = filled($matches[2] ?? null) ? $matches[2] : null;
    }

    public function render(PersonService $people, TextTranslator $translator)
    {
        try {
            $data = $people->find($this->personId, $this->slug);
        } catch (Throwable) {
            return view('livewire.person-detail', ['data' => null, 'failed' => true, 'biographyPending' => false])
                ->title('Orang');
        }

        abort_if($data === null, 404);

        $profile = $data['profile'];
        $needsTranslation = filled($profile['biography']) && $profile['biography_language'] !== config('services.tmdb.language');

        // Terjemahan yang sudah pernah dibuat langsung dipakai tanpa menunggu.
        if ($needsTranslation && $this->biographyId === null) {
            $this->biographyId = $translator->cached($profile['biography']);
        }

        return view('livewire.person-detail', [
            'data' => $data,
            'failed' => false,
            'biographyPending' => $needsTranslation && $this->biographyId === null
                && ! $this->biographyChecked && $translator->isConfigured(),
        ])->title($profile['name_latin'] ?? $profile['name']);
    }

    /**
     * Dipanggil lewat wire:init: biografi dari TMDB hampir selalu berbahasa
     * Inggris, jadi diterjemahkan setelah halaman tampil.
     */
    public function translateBiography(PersonService $people, TextTranslator $translator): void
    {
        $biography = $people->find($this->personId)['profile']['biography'] ?? null;

        if (filled($biography)) {
            $this->biographyId = $translator->toIndonesian($biography);
        }

        $this->biographyChecked = true;
    }
}
