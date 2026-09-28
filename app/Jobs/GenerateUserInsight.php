<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Ai\GeminiQuotaExceeded;
use App\Services\Insights\InsightService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Membuat insight satu pengguna di queue, supaya panggilan Gemini (belasan
 * detik) tidak menahan request mana pun.
 */
class GenerateUserInsight implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /** Satu insight per pengguna dalam antrean pada satu waktu. */
    public int $uniqueFor = 900;

    public function __construct(public readonly User $user) {}

    public const FAILED_QUOTA = 'quota';

    public const FAILED_ERROR = 'error';

    public static function pendingKey(int $userId): string
    {
        return "insight:pending:{$userId}";
    }

    public static function failedKey(int $userId): string
    {
        return "insight:failed:{$userId}";
    }

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    /**
     * Kuota gratis Gemini dibatasi per menit (lihat RateLimiter 'gemini').
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('gemini')];
    }

    public function handle(InsightService $insights): void
    {
        try {
            $insights->generate($this->user);
        } catch (GeminiQuotaExceeded $e) {
            // Kuota harian (model utama & cadangan) habis: percobaan ulang hari ini
            // hanya membuang waktu, jadi langsung gagal dengan alasan yang jelas.
            if ($e->daily) {
                $this->fail($e);

                return;
            }

            // Batas per menit: tunggu sesuai saran Gemini lalu coba lagi.
            $this->release($e->retryAfterSeconds ?? 60);

            return;
        }

        Cache::forget(self::pendingKey($this->user->id));
        Cache::forget(self::failedKey($this->user->id));
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::pendingKey($this->user->id));
        Cache::put(
            self::failedKey($this->user->id),
            $exception instanceof GeminiQuotaExceeded && $exception->daily ? self::FAILED_QUOTA : self::FAILED_ERROR,
            now()->addHours(6),
        );

        Log::warning('Gagal membuat insight AI.', ['user' => $this->user->id, 'reason' => $exception?->getMessage()]);
    }
}
