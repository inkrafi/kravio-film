<?php

namespace Tests\Feature;

use App\Livewire\ProfileComments;
use App\Models\ProfileComment;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create(['name' => 'Budi']);
    }

    private function form()
    {
        return Volt::actingAs($this->user)->test('profile.update-avatar-form');
    }

    public function test_the_settings_page_has_the_avatar_form(): void
    {
        $this->actingAs($this->user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSeeVolt('profile.update-avatar-form')
            ->assertSee('Foto Profil');
    }

    public function test_a_photo_is_cropped_square_resized_and_stored_as_webp(): void
    {
        $this->form()
            ->set('photo', UploadedFile::fake()->image('liburan.jpg', 1200, 800))
            ->assertHasNoErrors()
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('photo', null)
            ->assertDispatched('avatar-updated');

        $path = $this->user->fresh()->avatar_path;

        $this->assertStringStartsWith('avatars/'.$this->user->id.'-', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame([AvatarService::SIZE, AvatarService::SIZE], [$width, $height]);
        $this->assertSame(IMAGETYPE_WEBP, $type);
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        $this->form()->set('photo', UploadedFile::fake()->image('a.png', 300, 300))->call('save');
        $old = $this->user->fresh()->avatar_path;

        $this->form()->set('photo', UploadedFile::fake()->image('b.png', 300, 300))->call('save');
        $new = $this->user->fresh()->avatar_path;

        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_a_photo_can_be_removed(): void
    {
        $this->form()->set('photo', UploadedFile::fake()->image('a.png', 300, 300))->call('save');
        $path = $this->user->fresh()->avatar_path;

        $this->form()->call('remove');

        $this->assertNull($this->user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_images_are_rejected(): void
    {
        $this->form()
            ->set('photo', UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf'))
            ->assertHasErrors(['photo'])
            ->call('save')
            ->assertHasErrors(['photo']);

        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_oversized_and_tiny_images_are_rejected(): void
    {
        $this->form()
            ->set('photo', UploadedFile::fake()->image('besar.jpg', 500, 500)->size(3000))
            ->assertHasErrors(['photo' => 'max']);

        $this->form()
            ->set('photo', UploadedFile::fake()->image('kecil.png', 32, 32))
            ->assertHasErrors(['photo' => 'dimensions']);
    }

    public function test_cancelling_discards_the_selection(): void
    {
        $this->form()
            ->set('photo', UploadedFile::fake()->image('a.png', 300, 300))
            ->call('cancel')
            ->assertSet('photo', null);

        $this->assertNull($this->user->fresh()->avatar_path);
    }

    public function test_the_avatar_shows_on_the_profile_and_in_comments(): void
    {
        $this->form()->set('photo', UploadedFile::fake()->image('a.png', 300, 300))->call('save');
        $url = $this->user->fresh()->avatar_url;

        $this->assertSame(Storage::disk('public')->url($this->user->fresh()->avatar_path), $url);

        $this->actingAs($this->user)->get(route('profile.show', $this->user))->assertSee($url, escape: false);

        ProfileComment::factory()->create(['commenter_id' => $this->user->id, 'profile_user_id' => $this->user->id]);

        Livewire::actingAs($this->user)
            ->test(ProfileComments::class, ['user' => $this->user])
            ->assertSee($url, escape: false);
    }

    public function test_users_without_a_photo_show_their_initial(): void
    {
        $this->actingAs($this->user)
            ->get(route('profile.show', $this->user))
            ->assertDontSee('/storage/avatars/', escape: false)
            ->assertSee('Budi');
    }
}
