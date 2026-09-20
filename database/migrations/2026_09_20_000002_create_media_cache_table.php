<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_cache', function (Blueprint $table) {
            $table->id();
            $table->string('external_id');
            $table->enum('source', ['tmdb', 'jikan', 'anilist']);
            $table->enum('media_type', ['film', 'series', 'anime']);
            $table->string('title');
            $table->string('original_title')->nullable();
            $table->string('poster_url')->nullable();
            $table->string('backdrop_url')->nullable();
            $table->text('synopsis')->nullable();
            $table->smallInteger('year')->nullable();
            $table->date('released_on')->nullable();
            $table->json('genres')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'media_type', 'external_id']);
            $table->index('media_type');
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_cache');
    }
};
