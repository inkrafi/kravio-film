<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rating TMDB plus pemain & sutradara untuk halaman detail. Kolom sinkron
 * diganti nama karena kini mencakup lebih dari sekadar rating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->decimal('tmdb_rating', 3, 1)->nullable()->after('genres');
            $table->unsignedInteger('tmdb_votes')->nullable()->after('tmdb_rating');
            $table->json('credits')->nullable()->after('rotten_tomatoes_score');
            $table->renameColumn('ratings_synced_at', 'details_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->renameColumn('details_synced_at', 'ratings_synced_at');
            $table->dropColumn(['tmdb_rating', 'tmdb_votes', 'credits']);
        });
    }
};
