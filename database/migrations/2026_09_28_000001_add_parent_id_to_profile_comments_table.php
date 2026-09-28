<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Balasan komentar profil (satu tingkat). Menghapus komentar ikut menghapus balasannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_comments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('profile_user_id')
                ->constrained('profile_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('profile_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
