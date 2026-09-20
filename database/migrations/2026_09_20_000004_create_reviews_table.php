<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_cache_id')->constrained('media_cache')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('contains_spoiler')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'media_cache_id']);
            $table->index('media_cache_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
