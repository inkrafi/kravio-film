<?php

namespace Tests\Feature;

use App\Jobs\GenerateUserInsight;
use App\Livewire\InsightPage;
use App\Models\MediaCache;
use App\Models\User;
use App\Models\WatchEntry;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GeminiQuotaExceeded;
use App\Services\Ai\TextTranslator;
use App\Services\Insights\InsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GeminiQuotaTest extends TestCase
{
    use RefreshDatabase;

    private const SCHEMA = ['type' => 'OBJECT', 'properties' => ['text' => ['type' => 'STRING']]];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.key' => 'gemini-test-key',
            'services.gemini.model' => 'gemini-3.8-flash',
            'services.gemini.translation_model' => 'gemini-3.1-flash-lite',
            'services.gemini.fallback_model' => 'gemini-3.1-flash-lite',
        ]);

        Http::preventStrayRequests();
    }

    private static function quotaResponse(string $quotaId, string $retryDelay = '30s')
    {
        return Http::response(['error' => [
            'code' => 429,
            'message' => 'You exceeded your current quota',
            'details' => [
                ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [[
                    'quotaMetric' => 'generativelanguage.googleapis.com/generate_content_free_tier_requests',
                    'quotaId' => $quotaId,
                    'quotaValue' => '20',
                ]]],
                ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => $retryDelay],
            ],
        ]], 429);
    }

    private static function okResponse(array $json)
    {
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]]]);
    }

    public function test_a_daily_quota_falls_back_to_the_other_model_once(): void
    {
        Http::fake([
            '*/models/gemini-3.8-flash:generateContent' => self::quotaResponse('GenerateRequestsPerDayPerProjectPerModel-FreeTier'),
            '*/models/gemini-3.1-flash-lite:generateContent' => self::okResponse(['text' => 'halo']),
        ]);

        $gemini = app(GeminiClient::class);

        $this->assertSame(['text' => 'halo'], $gemini->generateJson('x', 'y', self::SCHEMA));
        $this->assertSame('gemini-3.1-flash-lite', $gemini->lastModel);
    }

    public function test_a_per_minute_quota_is_not_redirected_but_reported_with_its_delay(): void
    {
        Http::fake(['*' => self::quotaResponse('GenerateRequestsPerMinutePerProjectPerModel-FreeTier', '38.8s')]);

        try {
            app(GeminiClient::class)->generateJson('x', 'y', self::SCHEMA);
            $this->fail('Seharusnya melempar GeminiQuotaExceeded.');
        } catch (GeminiQuotaExceeded $e) {
            $this->assertFalse($e->daily);
            $this->assertSame(39, $e->retryAfterSeconds);
        }

        Http::assertSentCount(1);
    }

    public function test_translations_use_the_light_model_without_retries(): void
    {
        Http::fake(['*' => self::okResponse(['text' => 'Selamat pagi'])]);

        $this->assertSame('Selamat pagi', app(TextTranslator::class)->toIndonesian('Good morning'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-3.1-flash-lite'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'gemini-3.8-flash:'));
    }

    public function test_a_server_error_during_a_web_translation_is_not_retried(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'high demand']], 503)]);

        $this->assertNull(app(TextTranslator::class)->toIndonesian('Good morning'));

        Http::assertSentCount(1);
    }

    private function userWithHistory(): User
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            WatchEntry::factory()->create(['user_id' => $user->id, 'media_cache_id' => MediaCache::factory()->film()->create()->id, 'rating' => 7]);
        }

        return $user;
    }

    public function test_the_insight_records_the_model_that_actually_answered(): void
    {
        config(['services.tmdb.key' => null]);
        $user = $this->userWithHistory();

        Http::fake([
            '*/models/gemini-3.8-flash:generateContent' => self::quotaResponse('GenerateRequestsPerDayPerProjectPerModel-FreeTier'),
            '*/models/gemini-3.1-flash-lite:generateContent' => self::okResponse(['headline' => 'H', 'summary' => 'S', 'highlights' => [], 'recommendations' => []]),
            'graphql.anilist.co' => Http::response(['data' => ['Page' => ['pageInfo' => ['hasNextPage' => false], 'media' => []]]]),
        ]);

        $this->assertSame('gemini-3.1-flash-lite', app(InsightService::class)->generate($user)->model);
    }

    public function test_an_exhausted_daily_quota_fails_the_job_at_once_with_a_clear_reason(): void
    {
        config(['services.tmdb.key' => null]);
        $user = $this->userWithHistory();

        Http::fake([
            'generativelanguage.googleapis.com/*' => self::quotaResponse('GenerateRequestsPerDayPerProjectPerModel-FreeTier'),
            'graphql.anilist.co' => Http::response(['data' => ['Page' => ['pageInfo' => ['hasNextPage' => false], 'media' => []]]]),
        ]);

        Cache::put(GenerateUserInsight::pendingKey($user->id), true);

        // Dijalankan lewat queue sync: gagal di percobaan pertama, tanpa tiga kali mencoba.
        GenerateUserInsight::dispatch($user);

        $this->assertSame(GenerateUserInsight::FAILED_QUOTA, Cache::get(GenerateUserInsight::failedKey($user->id)));
        $this->assertFalse(Cache::has(GenerateUserInsight::pendingKey($user->id)));
        // Satu permintaan ke model utama + satu ke model cadangan.
        $this->assertCount(2, Http::recorded(fn ($request) => str_contains($request->url(), 'generativelanguage')));

        Livewire::actingAs($user)
            ->test(InsightPage::class)
            ->assertSee('kuota harian AI')
            ->assertSee('14.00');
    }
}
