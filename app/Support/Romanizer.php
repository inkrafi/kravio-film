<?php

namespace App\Support;

use Transliterator;

/**
 * Deteksi tulisan non-latin dan romanisasi cadangan tanpa AI.
 *
 * Romanisasi di sini sengaja konservatif: Hangul, kana, dan Han untuk bahasa
 * Mandarin bisa ditransliterasi dengan layak, tapi kanji Jepang tidak — ICU
 * membacanya dengan lafal Mandarin — jadi yang itu dibiarkan kosong dan
 * menunggu romanisasi dari Gemini (MediaLocalizationService).
 */
final class Romanizer
{
    /**
     * True kalau tidak ada huruf di luar aksara latin (angka & tanda baca bebas).
     */
    public static function isLatin(?string $text): bool
    {
        return $text === null || ! preg_match('/[^\p{Latin}\P{L}]/u', $text);
    }

    /**
     * @param  string|null  $language  Kode bahasa asli (ISO 639-1) kalau diketahui, mis. dari TMDB.
     */
    public static function romanize(?string $text, ?string $language = null): ?string
    {
        if (blank($text) || self::isLatin($text)) {
            return null;
        }

        $hasHan = (bool) preg_match('/\p{Han}/u', $text);
        $hasKana = (bool) preg_match('/[\p{Hiragana}\p{Katakana}]/u', $text);

        // Han tanpa kana bisa Mandarin atau Jepang; hanya aman kalau jelas Mandarin.
        if ($hasHan && ($hasKana || $language !== 'zh')) {
            return null;
        }

        $latin = Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate($text);

        if (! is_string($latin) || ! self::isLatin($latin)) {
            return null;
        }

        $latin = trim((string) preg_replace('/\s+/u', ' ', $latin));

        return $latin === '' ? null : ucwords($latin);
    }
}
