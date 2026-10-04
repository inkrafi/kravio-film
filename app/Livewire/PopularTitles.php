<?php

namespace App\Livewire;

use App\Enums\MediaType;
use App\Services\Media\TrendingService;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Judul populer di halaman Cari sebelum pengguna mengetik.
 *
 * Komponen terpisah dan lazy supaya request ke TMDB/AniList tidak pernah
 * menahan ketikan di kolom pencarian (request satu komponen Livewire antre).
 */
#[Lazy]
class PopularTitles extends Component
{
    /** Nilai MediaType, atau null untuk semua tipe. Mengikuti tab di MediaSearch. */
    #[Reactive]
    public ?string $type = null;

    public function placeholder()
    {
        return view('livewire.placeholders.popular-titles');
    }

    public function render(TrendingService $trending)
    {
        return view('livewire.popular-titles', [
            'popular' => $trending->titles(MediaType::tryFrom((string) $this->type)),
        ]);
    }
}
