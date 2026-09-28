<?php

use App\Support\Romanizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versi latin judul non-latin dan sinopsis berbahasa Indonesia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->string('title_latin')->nullable()->after('title');
            $table->string('original_title_latin')->nullable()->after('original_title');
            $table->text('synopsis_id')->nullable()->after('synopsis');
            // Sidik jari teks yang terakhir diterjemahkan; berubah => terjemahkan ulang.
            $table->string('localized_hash', 40)->nullable()->after('details_synced_at');
        });

        // Isi dari data yang sudah ada: romaji AniList/Jikan, lalu romanisasi cadangan.
        DB::table('media_cache')->orderBy('id')->each(function (object $row) {
            $raw = json_decode((string) $row->raw_payload, true) ?: [];
            $language = $raw['original_language'] ?? null;
            $romaji = match ($row->source) {
                'anilist' => $raw['title']['romaji'] ?? null,
                'jikan' => $raw['title'] ?? null,
                default => null,
            };
            $romaji = Romanizer::isLatin($romaji) ? $romaji : null;

            $updates = array_filter([
                'title_latin' => Romanizer::isLatin($row->title) ? null : ($romaji ?? Romanizer::romanize($row->title, $language)),
                'original_title_latin' => Romanizer::isLatin($row->original_title) ? null : ($romaji ?? Romanizer::romanize($row->original_title, $language)),
            ]);

            if ($updates !== []) {
                DB::table('media_cache')->where('id', $row->id)->update($updates);
            }
        });
    }

    public function down(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->dropColumn(['title_latin', 'original_title_latin', 'synopsis_id', 'localized_hash']);
        });
    }
};
