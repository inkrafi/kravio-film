<?php

use App\Jobs\GenerateUserInsight;
use App\Models\User;
use App\Services\Insights\InsightService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kravio:insights {--user= : Username satu pengguna saja} {--force : Abaikan jadwal & sidik jari data}', function (InsightService $insights) {
    if (! $insights->isConfigured()) {
        $this->error('GEMINI_API_KEY belum diisi.');

        return 1;
    }

    $query = User::query()->when($this->option('user'), fn ($query, $username) => $query->where('username', $username));
    $queued = 0;

    $query->lazyById()->each(function (User $user) use ($insights, &$queued) {
        $due = $this->option('force') ? $insights->hasEnoughData($user) : $insights->isDue($user);

        if ($due) {
            GenerateUserInsight::dispatch($user);
            $queued++;
        }
    });

    $this->info("{$queued} insight masuk antrean. Jalankan `php artisan queue:work` untuk memprosesnya.");

    return 0;
})->purpose('Antrekan insight AI mingguan untuk pengguna yang datanya berubah');

// Insight mingguan: tiap Senin pagi WIB. Pengguna tanpa perubahan data dilewati.
Schedule::command('kravio:insights')
    ->weeklyOn(1, '06:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
