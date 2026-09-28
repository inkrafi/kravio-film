<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anime tidak lagi punya media type sendiri: anime movie jadi `film`, anime
 * berepisode (TV, OVA, ONA, special, ...) jadi `series`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('media_cache')
            ->where('media_type', 'anime')
            ->orderBy('id')
            ->each(function (object $row) {
                $raw = json_decode((string) $row->raw_payload, true) ?: [];

                // AniList memakai `format` = MOVIE, Jikan memakai `type` = Movie.
                $isMovie = ($raw['format'] ?? null) === 'MOVIE' || ($raw['type'] ?? null) === 'Movie';

                DB::table('media_cache')
                    ->where('id', $row->id)
                    ->update(['media_type' => $isMovie ? 'film' : 'series']);
            });

        $this->setMediaTypes(['film', 'series']);
    }

    public function down(): void
    {
        // Data yang sudah dipindah tidak dikembalikan; kolomnya saja yang
        // kembali menerima `anime`.
        $this->setMediaTypes(['film', 'series', 'anime']);
    }

    /**
     * @param  list<string>  $types
     */
    private function setMediaTypes(array $types): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Di Postgres, enum Laravel = varchar + check constraint.
            $allowed = implode(', ', array_map(fn (string $type) => "'{$type}'", $types));

            DB::statement('alter table media_cache drop constraint if exists media_cache_media_type_check');
            DB::statement("alter table media_cache add constraint media_cache_media_type_check check (media_type in ({$allowed}))");

            return;
        }

        Schema::table('media_cache', function (Blueprint $table) use ($types) {
            $table->enum('media_type', $types)->change();
        });
    }
};
