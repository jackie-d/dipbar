<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FrontendTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_map_without_a_user(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Map')
            ->where('auth.user', null));
    }

    public function test_collections_page_requires_login(): void
    {
        $this->get('/collections')->assertRedirect('/login');
    }

    public function test_register_logs_the_user_in(): void
    {
        $this->post('/register', ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret123'])
            ->assertRedirect('/');

        $this->assertAuthenticated();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('auth.user.email', 'ada@example.com'));
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->get('/login')->assertRedirect('/');

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_the_session_authenticates_api_calls_from_the_frontend(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123']);

        // Same-host requests carrying the session cookie are treated as the logged-in user.
        $this->withHeader('Referer', 'http://localhost/')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }
}
