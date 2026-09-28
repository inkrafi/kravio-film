<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Klien tipis Gemini API (generateContent) yang selalu meminta balasan JSON
 * sesuai skema, supaya hasilnya bisa langsung dipakai tanpa parsing teks bebas.
 *
 * Kuota paket gratis dihitung per model per hari, jadi kalau kuota harian model
 * yang diminta habis, permintaan dialihkan sekali ke model cadangan.
 */
class GeminiClient
{
    /** Batas waktu default, untuk pemanggil di queue. */
    public const TIMEOUT_SECONDS = 45;

    /** Model yang benar-benar menjawab permintaan terakhir (bisa model cadangan). */
    public ?string $lastModel = null;

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @param  array<string, mixed>  $schema  responseSchema Gemini (subset OpenAPI).
     * @param  string|null  $model  Default: services.gemini.model.
     * @param  int  $retries  Percobaan ulang untuk error 5xx. Pemanggil di request web
     *                        sebaiknya 0 dengan timeout pendek, supaya tidak melewati
     *                        max_execution_time PHP.
     * @return array<string, mixed>
     *
     * @throws GeminiQuotaExceeded kalau kuota habis (juga di model cadangan).
     * @throws RuntimeException kalau Gemini gagal dihubungi atau balasannya bukan JSON.
     */
    public function generateJson(
        string $instruction,
        string $input,
        array $schema,
        float $temperature = 0.2,
        ?string $model = null,
        int $timeout = self::TIMEOUT_SECONDS,
        int $retries = 2,
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('GEMINI_API_KEY belum diisi.');
        }

        $model ??= (string) config('services.gemini.model');
        $fallback = (string) config('services.gemini.fallback_model');

        try {
            return $this->request($model, $instruction, $input, $schema, $temperature, $timeout, $retries);
        } catch (GeminiQuotaExceeded $e) {
            if (! $e->daily || $fallback === '' || $fallback === $model) {
                throw $e;
            }

            Log::info('Kuota harian Gemini habis, beralih ke model cadangan.', ['model' => $model, 'fallback' => $fallback]);

            return $this->request($fallback, $instruction, $input, $schema, $temperature, $timeout, $retries);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function request(string $model, string $instruction, string $input, array $schema, float $temperature, int $timeout, int $retries): array
    {
        $url = rtrim((string) config('services.gemini.base_url'), '/').'/models/'.$model.':generateContent';

        $response = Http::timeout($timeout)
            ->retry($retries + 1, 1000, fn ($exception) => $exception->response?->status() >= 500, throw: false)
            ->withHeaders(['x-goog-api-key' => (string) config('services.gemini.key')])
            ->acceptJson()
            ->post($url, [
                'systemInstruction' => ['parts' => [['text' => $instruction]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $input]]]],
                'generationConfig' => [
                    'temperature' => $temperature,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $schema,
                    // Terjemahan & ringkasan tidak butuh "berpikir"; lebih cepat & hemat kuota.
                    'thinkingConfig' => ['thinkingBudget' => 0],
                ],
            ]);

        if ($response->status() === 429) {
            throw $this->quotaException($response, $model);
        }

        if ($response->failed()) {
            throw new RuntimeException('Gemini membalas '.$response->status().': '.Arr::get($response->json() ?? [], 'error.message', 'tanpa pesan'));
        }

        $text = Arr::get($response->json(), 'candidates.0.content.parts.0.text');
        $data = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($data)) {
            throw new RuntimeException('Balasan Gemini bukan JSON yang valid (finishReason: '.Arr::get($response->json(), 'candidates.0.finishReason', '?').').');
        }

        $this->lastModel = $model;

        return $data;
    }

    private function quotaException(Response $response, string $model): GeminiQuotaExceeded
    {
        $details = collect(Arr::get($response->json() ?? [], 'error.details', []));

        $daily = $details
            ->flatMap(fn ($detail) => $detail['violations'] ?? [])
            ->contains(fn ($violation) => str_contains((string) ($violation['quotaId'] ?? ''), 'PerDay'));

        $retryDelay = $details->firstWhere(fn ($detail) => isset($detail['retryDelay']))['retryDelay'] ?? null;

        return new GeminiQuotaExceeded(
            "Kuota Gemini habis ({$model}".($daily ? ', harian' : '').').',
            daily: $daily,
            retryAfterSeconds: $retryDelay !== null ? (int) ceil((float) rtrim($retryDelay, 's')) : null,
            model: $model,
        );
    }
}
