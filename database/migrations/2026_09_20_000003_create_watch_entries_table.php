<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_cache_id')->constrained('media_cache')->cascadeOnDelete();
            $table->enum('status', ['watched', 'watchlist']);
            $table->unsignedSmallInteger('rating')->nullable();
            $table->timestamp('watched_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'media_cache_id']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'watched_at']);
        });

        DB::statement('ALTER TABLE watch_entries ADD CONSTRAINT watch_entries_rating_range CHECK (rating IS NULL OR (rating >= 1 AND rating <= 10))');
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_entries');
    }
};
