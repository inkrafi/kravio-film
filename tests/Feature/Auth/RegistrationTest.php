<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('username', 'test_user')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'test_user']);
    }

    public function test_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'sudah_dipakai']);

        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('username', 'sudah_dipakai')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors(['username' => 'unique']);

        $this->assertGuest();
    }

    public function test_username_rejects_spaces_and_uppercase(): void
    {
        Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('username', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasErrors('username');

        $this->assertGuest();
    }
}
