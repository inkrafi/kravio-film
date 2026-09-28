<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Terjemahan teks bebas (mis. biografi orang) ke bahasa Indonesia lewat Gemini.
 * Hasil dicache permanen per isi teks; kegagalan tidak dicache supaya dicoba lagi.
 */
class TextTranslator
{
    private const INSTRUCTION = <<<'TEXT'
        Terjemahkan teks ke bahasa Indonesia yang alami dan enak dibaca untuk pembaca umum.
        Pertahankan nama orang, nama tempat, dan judul karya apa adanya. Jangan menambah atau mengurangi isi.
        Kalau teks sudah berbahasa Indonesia, kembalikan apa adanya.
        TEXT;

    private const SCHEMA = [
        'type' => 'OBJECT',
        'properties' => ['text' => ['type' => 'STRING']],
        'required' => ['text'],
    ];

    private const WEB_TIMEOUT_SECONDS = 20;

    public function __construct(private readonly GeminiClient $gemini) {}

    public function isConfigured(): bool
    {
        return $this->gemini->isConfigured();
    }

    public function cached(string $text): ?string
    {
        return Cache::get($this->key($text));
    }

    public function toIndonesian(string $text): ?string
    {
        if (($cached = $this->cached($text)) !== null) {
            return $cached;
        }

        if (! $this->isConfigured() || trim($text) === '') {
            return null;
        }

        try {
            $translated = trim((string) ($this->gemini->generateJson(
                self::INSTRUCTION,
                $text,
                self::SCHEMA,
                model: (string) config('services.gemini.translation_model'),
                // Dipanggil dari request web (wire:init).
                timeout: self::WEB_TIMEOUT_SECONDS,
                retries: 0,
            )['text'] ?? ''));
        } catch (Throwable $e) {
            Log::warning('Gagal menerjemahkan teks lewat Gemini.', ['reason' => $e->getMessage()]);

            return null;
        }

        if ($translated === '') {
            return null;
        }

        Cache::forever($this->key($text), $translated);

        return $translated;
    }

    private function key(string $text): string
    {
        return 'translation:id:'.sha1($text);
    }
}
