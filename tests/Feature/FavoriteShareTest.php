<?php

namespace Tests\Feature;

use App\Livewire\UserProfile;
use App\Models\Favorite;
use App\Models\MediaCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FavoriteShareTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Rafi', 'username' => 'rafi', 'bio' => 'Absolute cinema.']);

        Http::preventStrayRequests();
    }

    /**
     * @param  list<string>  $titles
     */
    private function favorites(array $titles, array $attributes = []): void
    {
        foreach ($titles as $order => $title) {
            Favorite::factory()->for($this->user)->create([
                'media_cache_id' => MediaCache::factory()->create(['title' => $title] + $attributes)->id,
                'sort_order' => $order,
            ]);
        }
    }

    private function poster(): string
    {
        $image = imagecreatetruecolor(20, 30);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function test_guests_can_open_the_shared_favorites_with_a_link_preview(): void
    {
        $this->favorites(['Interstellar', 'My Mister']);

        $this->get(route('favorites.share', $this->user))
            ->assertOk()
            ->assertSee('Favorit Rafi')
            ->assertSeeInOrder(['Interstellar', 'My Mister'])
            ->assertSee('Absolute cinema.')
            ->assertSee('property="og:image" content="'.route('favorites.image', ['user' => $this->user, 'format' => 'kotak']), escape: false)
            ->assertSee('Bagikan favoritmu sendiri');
    }

    public function test_a_user_without_favorites_has_nothing_to_share(): void
    {
        $this->get(route('favorites.share', $this->user))->assertNotFound();
        $this->get(route('favorites.image', ['user' => $this->user, 'format' => 'kotak']))->assertNotFound();
    }

    public function test_share_images_are_rendered_in_both_formats_and_reused(): void
    {
        Storage::fake('local');
        Http::fake(['image.tmdb.org/*' => Http::response($this->poster(), 200, ['Content-Type' => 'image/png'])]);

        $this->favorites(['Satu', 'Dua', 'Tiga'], ['poster_url' => 'https://image.tmdb.org/t/p/w342/poster.jpg']);

        foreach (['kotak' => [1080, 1080], 'story' => [1080, 1920]] as $format => [$width, $height]) {
            $response = $this->get(route('favorites.image', ['user' => $this->user, 'format' => $format]))
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png');

            $size = getimagesizefromstring(file_get_contents($response->baseResponse->getFile()->getPathname()));
            $this->assertSame([$width, $height], [$size[0], $size[1]]);
        }

        // Gambar yang sama dipakai ulang tanpa mengunduh poster lagi.
        $sent = count(Http::recorded());
        $this->get(route('favorites.image', ['user' => $this->user, 'format' => 'kotak']))->assertOk();
        $this->assertCount($sent, Http::recorded());

        $this->get(route('favorites.image', ['user' => $this->user, 'format' => 'story']).'?unduh=1')
            ->assertOk()
            ->assertDownload('favorit-rafi-story.png');
    }

    public function test_changing_favorites_replaces_the_old_image(): void
    {
        Storage::fake('local');
        Http::fake(['*' => Http::response($this->poster())]);

        $this->favorites(['Satu'], ['poster_url' => 'https://image.tmdb.org/t/p/w342/a.jpg']);
        $this->get(route('favorites.image', ['user' => $this->user, 'format' => 'kotak']))->assertOk();

        $this->favorites(['Dua'], ['poster_url' => 'https://image.tmdb.org/t/p/w342/b.jpg']);
        $this->get(route('favorites.image', ['user' => $this->user, 'format' => 'kotak']))->assertOk();

        $this->assertCount(1, Storage::disk('local')->files('favorit'));
    }

    public function test_only_the_owner_sees_the_share_button_on_the_profile(): void
    {
        $this->favorites(['Interstellar']);

        Livewire::actingAs($this->user)
            ->test(UserProfile::class, ['user' => $this->user])
            ->assertSee('Bagikan')
            ->assertSee(route('favorites.share', $this->user))
            ->assertSee('Unduh story');

        Livewire::actingAs(User::factory()->create())
            ->test(UserProfile::class, ['user' => $this->user])
            ->assertSee('Interstellar')
            ->assertDontSee('Unduh story');
    }

    public function test_the_share_button_is_hidden_without_favorites(): void
    {
        Livewire::actingAs($this->user)
            ->test(UserProfile::class, ['user' => $this->user])
            ->assertSee('Belum ada favorit.')
            ->assertDontSee('Unduh kotak');
    }
}
