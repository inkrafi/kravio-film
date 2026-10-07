<?php

namespace Tests\Feature;

use App\Models\MediaCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_guests_see_the_pitch_and_can_sign_up(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Film bagus selalu kursi penuh.')
            ->assertSee('Cara skor dihitung')
            ->assertSee('Daftar gratis')
            ->assertSee(route('register'))
            ->assertSee('Kursi Penuh dibuat oleh Kravio.');
    }

    public function test_signed_in_users_are_pointed_to_their_home(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Buka beranda')
            ->assertDontSee('Daftar gratis');
    }

    public function test_popular_titles_come_from_the_cache_without_calling_any_api(): void
    {
        $media = MediaCache::factory()->create(['title' => 'Judul Ramai', 'poster_url' => 'https://image.tmdb.org/t/p/w342/a.jpg']);
        Cache::put('trending:semua:'.config('services.tmdb.language'), ['ids' => [$media->id], 'failed' => []]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Sedang ramai minggu ini')
            ->assertSee('Judul Ramai');

        Http::assertNothingSent();
    }

    public function test_the_popular_section_is_skipped_when_nothing_is_cached(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Sedang ramai minggu ini');

        Http::assertNothingSent();
    }
}
