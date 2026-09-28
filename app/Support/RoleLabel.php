<?php

namespace App\Support;

/**
 * Istilah peran bahasa Inggris dari TMDB dalam bahasa Indonesia. Nama tokoh
 * dibiarkan apa adanya; yang diterjemahkan hanya istilah bakunya, mis.
 * "Self" (tampil sebagai diri sendiri di dokumenter/talk show), "(voice)".
 */
final class RoleLabel
{
    /** Peran yang berarti tampil sebagai diri sendiri. */
    private const SELF = '(?:self|himself|herself|themselves|themself)';

    /** @var array<string, string> Keterangan dalam kurung, huruf kecil. */
    private const NOTES = [
        'voice' => 'suara',
        'uncredited' => 'tanpa kredit',
        'archive footage' => 'cuplikan arsip',
        'archival footage' => 'cuplikan arsip',
        'credit only' => 'hanya kredit',
        'cameo' => 'cameo',
    ];

    /** @var array<string, string> Istilah peran utuh, huruf kecil. */
    private const TERMS = [
        'host' => 'Pembawa acara',
        'co-host' => 'Pembawa acara pendamping',
        'guest' => 'Bintang tamu',
        'narrator' => 'Narator',
        'presenter' => 'Presenter',
        'judge' => 'Juri',
        'contestant' => 'Peserta',
        'performer' => 'Penampil',
        'interviewee' => 'Narasumber',
        'various' => 'Berbagai peran',
        'various characters' => 'Berbagai tokoh',
        'additional voices' => 'Suara tambahan',
    ];

    /**
     * @param  bool  $inSentence  True untuk "sebagai …": "dirinya sendiri" ditulis huruf kecil.
     */
    public static function translate(?string $role, bool $inSentence = false): ?string
    {
        if (blank($role)) {
            return null;
        }

        $role = trim($role);

        // "Self", "Himself - Host", "Self (archive footage)".
        $role = preg_replace_callback(
            '/^'.self::SELF.'\b(?:\s*[-–:]\s*(?<rest>[^(]+))?/iu',
            fn (array $m) => 'Dirinya sendiri'.(isset($m['rest']) && trim($m['rest']) !== '' ? ' – '.self::term(trim($m['rest'])) : ''),
            $role,
        ) ?? $role;

        // Keterangan dalam kurung: "Sakuragi (voice)" → "Sakuragi (suara)".
        $role = preg_replace_callback(
            '/\(([^)]+)\)/u',
            fn (array $m) => '('.(self::NOTES[mb_strtolower(trim($m[1]))] ?? $m[1]).')',
            $role,
        ) ?? $role;

        // Peran berupa istilah utuh, mis. "Host" atau "Narrator (voice)".
        $role = preg_replace_callback(
            '/^([^(]+?)(\s*\(.*\))?$/u',
            fn (array $m) => self::term($m[1]).($m[2] ?? ''),
            $role,
        ) ?? $role;

        if ($inSentence && str_starts_with($role, 'Dirinya sendiri')) {
            $role = 'd'.mb_substr($role, 1);
        }

        return $role;
    }

    private static function term(string $text): string
    {
        return self::TERMS[mb_strtolower(trim($text))] ?? $text;
    }
}
