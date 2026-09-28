<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * List buatan pengguna (mis. "Top 10 Anime 2024"): isi berurutan dengan catatan
 * per judul, visibilitas per list, plus like, komentar, dan salin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->enum('visibility', ['public', 'friends', 'private'])->default('public');
            // Tampilkan nomor urut (list peringkat seperti "Top 10").
            $table->boolean('is_ranked')->default(false);
            $table->foreignId('copied_from_id')->nullable()->constrained('media_lists')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index(['visibility', 'updated_at']);
        });

        Schema::create('media_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_cache_id')->constrained('media_cache')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['media_list_id', 'media_cache_id']);
            $table->index(['media_list_id', 'position']);
        });

        Schema::create('media_list_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['media_list_id', 'user_id']);
        });

        Schema::create('media_list_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['media_list_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_list_comments');
        Schema::dropIfExists('media_list_likes');
        Schema::dropIfExists('media_list_items');
        Schema::dropIfExists('media_lists');
    }
};
