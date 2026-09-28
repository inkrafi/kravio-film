<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Gemini membalas 429. Kuota harian (paket gratis: per model per hari) tidak
 * ada gunanya dicoba ulang hari yang sama; kuota per menit cukup ditunggu.
 */
class GeminiQuotaExceeded extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $daily,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $model = null,
    ) {
        parent::__construct($message);
    }
}
