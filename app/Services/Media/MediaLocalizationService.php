<?php

namespace App\Services\Media;

use App\Models\MediaCache;
use App\Services\Ai\GeminiClient;
use App\Support\Romanizer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Melokalkan satu judul untuk halaman detail lewat Gemini, dalam satu request:
 *
 * - sinopsis diterjemahkan ke bahasa Indonesia (sinopsis asli tetap disimpan);
 * - judul/judul asli beraksara non-latin diberi versi latin;
 * - nama sutradara, kreator, dan pemain beraksara non-latin diberi ejaan latin.
 *
 * Hasilnya disimpan permanen dan hanya diulang kalau teks sumbernya berubah
 * (lihat localized_hash).
 */
class MediaLocalizationService
{
    private const INSTRUCTION = <<<'TEXT'
        Kamu penerjemah dan ahli romanisasi untuk katalog film & series berbahasa Indonesia.

        - synopsis: terjemahkan ke bahasa Indonesia yang alami dan enak dibaca. Pertahankan nama tokoh, nama tempat, dan judul apa adanya. Jangan menambah atau mengurangi isi. Kalau sudah berbahasa Indonesia, kembalikan apa adanya. Kalau tidak diberikan, isi null.
        - title_latin: hanya kalau "title" diberikan. Pakai judul internasional resmi berhuruf latin kalau judul itu dikenal luas (mis. judul bahasa Inggrisnya); kalau tidak ada, romanisasi. Kalau tidak diberikan, isi null.
        - original_title_latin: hanya kalau "original_title" diberikan. Romanisasi judul asli apa adanya (bukan terjemahan). Kalau tidak diberikan, isi null.
        - names: untuk setiap nama orang di "names", beri ejaan latin yang paling umum dipakai di kredit film internasional (Korea: marga di depan, mis. "Lee Sun-kyun"; Jepang: seperti yang lazim dikreditkan, mis. "Mackenyu Arata"; Mandarin: pinyin tanpa nada).

        Romanisasi: Korea pakai Revised Romanization, Jepang pakai Hepburn, Mandarin pakai Hanyu Pinyin tanpa nada.
        Semua hasil title_latin, original_title_latin, dan latin wajib memakai huruf latin saja.
        TEXT;

    private const SCHEMA = [
        'type' => 'OBJECT',
        'properties' => [
            'synopsis' => ['type' => 'STRING', 'nullable' => true],
            'title_latin' => ['type' => 'STRING', 'nullable' => true],
            'original_title_latin' => ['type' => 'STRING', 'nullable' => true],
            'names' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'original' => ['type' => 'STRING'],
                        'latin' => ['type' => 'STRING'],
                    ],
                    'required' => ['original', 'latin'],
                ],
            ],
        ],
        'required' => ['synopsis', 'title_latin', 'original_title_latin', 'names'],
    ];

    private const WEB_TIMEOUT_SECONDS = 20;

    public function __construct(private readonly GeminiClient $gemini) {}

    public function isConfigured(): bool
    {
        return $this->gemini->isConfigured();
    }

    public function needsLocalization(MediaCache $media): bool
    {
        return $this->isConfigured() && $media->localized_hash !== $this->hash($media);
    }

    /**
     * Kegagalan (kuota habis, jaringan) hanya dicatat; judul akan dicoba lagi di
     * kunjungan berikutnya karena localized_hash belum diperbarui.
     */
    public function localize(MediaCache $media): MediaCache
    {
        if (! $this->needsLocalization($media)) {
            return $media;
        }

        $input = $this->input($media);

        // Semua sudah latin dan tidak ada sinopsis: tidak ada yang perlu dikerjakan.
        if ($input === []) {
            $media->forceFill(['localized_hash' => $this->hash($media)])->save();

            return $media;
        }

        try {
            $result = $this->gemini->generateJson(
                self::INSTRUCTION,
                json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                self::SCHEMA,
                // Berjalan di request web (wire:init): jangan sampai melewati
                // max_execution_time PHP; kalau gagal, dicoba lagi di kunjungan berikutnya.
                model: (string) config('services.gemini.translation_model'),
                timeout: self::WEB_TIMEOUT_SECONDS,
                retries: 0,
            );
        } catch (Throwable $e) {
            Log::warning('Gagal melokalkan judul lewat Gemini.', ['media' => $media->sourceKey(), 'reason' => $e->getMessage()]);

            return $media;
        }

        $updates = [];

        if (isset($input['synopsis']) && filled($result['synopsis'] ?? null)) {
            $updates['synopsis_id'] = trim($result['synopsis']);
        }

        if (isset($input['title']) && $this->isLatinText($result['title_latin'] ?? null)) {
            $updates['title_latin'] = trim($result['title_latin']);
        }

        if (isset($input['original_title']) && $this->isLatinText($result['original_title_latin'] ?? null)) {
            $updates['original_title_latin'] = trim($result['original_title_latin']);
        }

        if (isset($input['names'])) {
            $updates['credits'] = $this->withLatinNames($media->credits ?? [], $result['names'] ?? []);
        }

        $media->forceFill($updates);
        // Dihitung setelah nama latin terpasang, jadi nama yang sudah beres
        // tidak lagi dianggap "perlu dikerjakan".
        $media->forceFill(['localized_hash' => $this->hash($media)])->save();

        return $media;
    }

    /**
     * Hanya bagian yang memang perlu dikerjakan yang dikirim ke Gemini.
     *
     * @return array<string, mixed>
     */
    private function input(MediaCache $media): array
    {
        return array_filter([
            'title' => Romanizer::isLatin($media->title) ? null : $media->title,
            'original_title' => Romanizer::isLatin($media->original_title) ? null : $media->original_title,
            'synopsis' => filled($media->synopsis) ? $media->synopsis : null,
            'names' => $this->namesNeedingLatin($media) ?: null,
        ]);
    }

    /**
     * Nama non-latin yang belum pernah diberi ejaan latin. Kunci name_latin
     * yang ada (walau null) menandai nama itu sudah pernah dicoba.
     *
     * @return list<string>
     */
    private function namesNeedingLatin(MediaCache $media): array
    {
        return collect(['directors', 'creators', 'cast'])
            ->flatMap(fn (string $group) => $media->people($group))
            ->reject(fn (array $person) => array_key_exists('name_latin', $person) || Romanizer::isLatin($person['name']))
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $credits
     * @param  list<array{original?: string, latin?: string}>  $names
     * @return array<string, list<array<string, mixed>>>
     */
    private function withLatinNames(array $credits, array $names): array
    {
        $latin = collect($names)
            ->filter(fn ($pair) => is_string($pair['original'] ?? null) && $this->isLatinText($pair['latin'] ?? null))
            ->mapWithKeys(fn ($pair) => [$pair['original'] => trim($pair['latin'])]);

        foreach ($credits as $group => $people) {
            foreach ($people as $i => $person) {
                $name = $person['name'] ?? null;

                if (is_string($name) && ! Romanizer::isLatin($name) && ! array_key_exists('name_latin', $person)) {
                    $credits[$group][$i]['name_latin'] = $latin->get($name) ?? Romanizer::romanize($name);
                }
            }
        }

        return $credits;
    }

    private function isLatinText(mixed $text): bool
    {
        return is_string($text) && trim($text) !== '' && Romanizer::isLatin($text);
    }

    /**
     * Sidik jari teks sumber: berubah kalau judul atau sinopsis berubah, atau
     * ada nama non-latin baru (mis. credits baru diambil), sehingga terjemahan
     * diperbarui.
     */
    private function hash(MediaCache $media): string
    {
        return sha1(json_encode([
            $media->title,
            $media->original_title,
            $media->synopsis,
            $this->namesNeedingLatin($media),
        ], JSON_UNESCAPED_UNICODE));
    }
}
