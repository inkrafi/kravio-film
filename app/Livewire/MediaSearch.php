<?php

namespace App\Livewire;

use App\Enums\MediaType;
use App\Services\Media\Dto\MediaSearchResults;
use App\Services\Media\MediaSearchService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Search gabungan: satu kolom pencarian, hasil film/series bercampur (anime
 * ikut masuk Film atau Series), bisa dipersempit lewat tab filter.
 */
#[Layout('layouts.app')]
#[Title('Cari Film & Series')]
class MediaSearch extends Component
{
    public const ALL = 'semua';

    private const MIN_QUERY_LENGTH = 2;

    private const LIMIT = 24;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'tipe', except: self::ALL)]
    public string $type = self::ALL;

    public function mount(): void
    {
        $this->type = $this->normalizedType();
    }

    /**
     * Tab filter: "Semua" plus satu tab per media type.
     *
     * @return array<string, string>
     */
    public function getTabsProperty(): array
    {
        $tabs = [self::ALL => 'Semua'];

        foreach (MediaType::cases() as $case) {
            $tabs[$case->value] = $case->label();
        }

        return $tabs;
    }

    public function selectType(string $type): void
    {
        $this->type = $type === self::ALL ? self::ALL : (MediaType::tryFrom($type)?->value ?? self::ALL);
    }

    public function clear(): void
    {
        $this->query = '';
        $this->type = self::ALL;
    }

    public function getHasQueryProperty(): bool
    {
        return mb_strlen(trim($this->query)) >= self::MIN_QUERY_LENGTH;
    }

    public function render(MediaSearchService $search)
    {
        $results = $this->hasQuery
            ? $search->search($this->query, $this->selectedTypes(), self::LIMIT)
            : MediaSearchResults::empty();

        return view('livewire.media-search', [
            'results' => $results,
            'minQueryLength' => self::MIN_QUERY_LENGTH,
        ]);
    }

    /**
     * @return list<MediaType>|null
     */
    private function selectedTypes(): ?array
    {
        $type = MediaType::tryFrom($this->normalizedType());

        return $type ? [$type] : null;
    }

    private function normalizedType(): string
    {
        return MediaType::tryFrom($this->type)?->value ?? self::ALL;
    }
}
