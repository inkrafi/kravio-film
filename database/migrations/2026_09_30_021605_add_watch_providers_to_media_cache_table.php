<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform tempat judul bisa ditonton (streaming, sewa, beli) dari TMDB/JustWatch,
 * ikut disinkronkan bersama rating dan credits di halaman detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->json('watch_providers')->nullable()->after('credits');
        });
    }

    public function down(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->dropColumn('watch_providers');
        });
    }
};
