<?php

use App\Models\MediaCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slug judul untuk URL `/film/{slug}` dan `/series/{slug}`. Unik per media type,
 * jadi film dan series boleh punya slug yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('media_type');
            $table->unique(['media_type', 'slug']);
        });

        // Judul yang lebih dulu tersimpan mendapat slug tanpa akhiran.
        MediaCache::query()->whereNull('slug')->orderBy('id')->each(
            fn (MediaCache $media) => $media->assignSlug(),
        );
    }

    public function down(): void
    {
        Schema::table('media_cache', function (Blueprint $table) {
            $table->dropUnique(['media_type', 'slug']);
            $table->dropColumn('slug');
        });
    }
};
