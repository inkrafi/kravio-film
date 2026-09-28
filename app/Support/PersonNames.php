<?php

namespace App\Support;

use App\Models\MediaCache;

/**
 * Ejaan latin orang yang namanya beraksara non-latin, diambil dari credits
 * judul yang sudah dilokalkan Gemini (MediaLocalizationService) — supaya
 * pencarian, halaman detail, dan halaman orang menulis nama yang sama.
 */
final class PersonNames
{
    private const GROUPS = ['cast', 'directors', 'creators'];

    public static function latinFromCredits(int $personId): ?string
    {
        $rows = MediaCache::query()
            ->where(function ($query) use ($personId) {
                foreach (self::GROUPS as $group) {
                    $query->orWhereJsonContains("credits->{$group}", [['id' => $personId]]);
                }
            })
            ->whereNotNull('localized_hash')
            ->limit(10)
            ->get(['id', 'credits']);

        foreach ($rows as $media) {
            foreach (self::GROUPS as $group) {
                foreach ($media->people($group) as $person) {
                    if (($person['id'] ?? null) === $personId && filled($person['name_latin'] ?? null)) {
                        return $person['name_latin'];
                    }
                }
            }
        }

        return null;
    }
}
