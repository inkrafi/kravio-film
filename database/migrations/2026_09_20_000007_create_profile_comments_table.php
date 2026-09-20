<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commenter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('profile_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['profile_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_comments');
    }
};
