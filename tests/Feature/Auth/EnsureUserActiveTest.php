<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureUserActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_open_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_user_deactivated_mid_session_is_blocked_and_logged_out(): void
    {
        $user = User::factory()->create();

        // Sesi terbuka, lalu akun dinonaktifkan oleh admin.
        $this->actingAs($user);
        $user->update(['status' => UserStatus::Nonaktif->value]);

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
