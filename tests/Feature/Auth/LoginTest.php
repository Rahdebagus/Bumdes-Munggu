<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_reachable(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('rahasia123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('rahasia123'),
        ]);

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'salah',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login_even_with_correct_password(): void
    {
        User::factory()->nonaktif()->create([
            'email' => 'nonaktif@example.test',
            'password' => Hash::make('rahasia123'),
        ]);

        $this->post('/login', [
            'email' => 'nonaktif@example.test',
            'password' => 'rahasia123',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_public_registration_route_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }
}
