<?php

namespace Database\Seeders;

use App\Enums\FriendshipStatus;
use App\Enums\WatchStatus;
use App\Models\Favorite;
use App\Models\Friendship;
use App\Models\MediaCache;
use App\Models\Review;
use App\Models\User;
use App\Models\WatchEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh untuk mencoba gating pertemanan secara manual — perlu lebih dari
 * satu akun supaya bedanya "sudah berteman" dan "belum" kelihatan.
 *
 * Judul diambil dari media_cache yang sudah ada (hasil pencarian sungguhan),
 * jadi jalankan setelah beberapa kali mencari di aplikasi.
 *
 * php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $media = MediaCache::query()->inRandomOrder()->take(12)->get();

        if ($media->count() < 6) {
            $this->command->warn('media_cache masih terlalu sedikit. Cari beberapa judul dulu di /search, lalu jalankan seeder ini lagi.');

            return;
        }

        $budi = $this->makeUser('budi', 'Budi Santoso', 'Maraton anime tiap akhir pekan.');
        $citra = $this->makeUser('citra', 'Citra Lestari', 'Drama Korea dan film festival.');
        $dimas = $this->makeUser('dimas', 'Dimas Prayoga', 'Masih belum jadi temanmu — profilnya terkunci.');

        foreach ([$budi, $citra, $dimas] as $index => $user) {
            $slice = $media->slice($index * 3, 6);

            foreach ($slice as $offset => $title) {
                $watched = $offset % 3 !== 0;

                WatchEntry::updateOrCreate(
                    ['user_id' => $user->id, 'media_cache_id' => $title->id],
                    [
                        'status' => $watched ? WatchStatus::Watched : WatchStatus::Watchlist,
                        'rating' => $watched ? random_int(6, 10) : null,
                        'watched_at' => $watched ? now()->subDays(random_int(1, 90)) : null,
                    ],
                );
            }

            foreach ($slice->take(Favorite::MAX_PER_USER) as $order => $title) {
                Favorite::updateOrCreate(
                    ['user_id' => $user->id, 'media_cache_id' => $title->id],
                    ['sort_order' => $order],
                );
            }

            Review::updateOrCreate(
                ['user_id' => $user->id, 'media_cache_id' => $slice->first()->id],
                ['body' => 'Salah satu tontonan paling berkesan buat saya tahun ini.', 'contains_spoiler' => false],
            );
        }

        // Budi sudah berteman dengan Citra; Dimas sengaja dibiarkan asing.
        Friendship::updateOrCreate(
            ['user_id' => $budi->id, 'friend_id' => $citra->id],
            ['status' => FriendshipStatus::Accepted, 'accepted_at' => now()],
        );

        if ($owner = User::query()->whereNotIn('id', [$budi->id, $citra->id, $dimas->id])->oldest('id')->first()) {
            // Permintaan masuk yang menunggu jawaban akun utamamu.
            Friendship::updateOrCreate(
                ['user_id' => $citra->id, 'friend_id' => $owner->id],
                ['status' => FriendshipStatus::Pending],
            );

            $this->command->info("Citra mengirim permintaan pertemanan ke @{$owner->username}.");
        }

        $this->command->info('Akun demo: budi / citra / dimas — semuanya dengan password "password".');
    }

    private function makeUser(string $username, string $name, string $bio): User
    {
        return User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => "{$username}@kravio.test",
                'bio' => $bio,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
