<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rating IMDb & Rotten Tomatoes (via OMDb), dicache per judul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->string('imdb_id', 16)->nullable()->after('external_id');
            $table->decimal('imdb_rating', 3, 1)->nullable()->after('genres');
            $table->unsignedInteger('imdb_votes')->nullable()->after('imdb_rating');
            $table->unsignedTinyInteger('rotten_tomatoes_score')->nullable()->after('imdb_votes');
            $table->timestamp('ratings_synced_at')->nullable()->after('synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->dropColumn(['imdb_id', 'imdb_rating', 'imdb_votes', 'rotten_tomatoes_score', 'ratings_synced_at']);
        });
    }
};
