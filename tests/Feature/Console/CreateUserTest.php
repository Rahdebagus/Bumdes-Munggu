<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_user_via_options(): void
    {
        $this->artisan('app:create-user', [
            '--nama' => 'Admin Uji',
            '--email' => 'admin.uji@example.test',
            '--role' => 'admin',
            '--password' => 'rahasia123',
        ])->assertSuccessful();

        $user = User::where('email', 'admin.uji@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('Admin Uji', $user->nama);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame(UserStatus::Aktif, $user->status);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_prompts_for_hidden_password_interactively(): void
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Nama lengkap', 'Pimpinan Uji')
            ->expectsQuestion('Email', 'pimpinan.uji@example.test')
            ->expectsChoice('Role', 'pimpinan', UserRole::values())
            ->expectsQuestion('Password (tersembunyi)', 'rahasia123')
            ->expectsQuestion('Ulangi password', 'rahasia123')
            ->assertSuccessful();

        $user = User::where('email', 'pimpinan.uji@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame(UserRole::Pimpinan, $user->role);
    }

    public function test_rejects_mismatched_password_confirmation(): void
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Nama lengkap', 'Salah Konfirmasi')
            ->expectsQuestion('Email', 'salah@example.test')
            ->expectsChoice('Role', 'admin', UserRole::values())
            ->expectsQuestion('Password (tersembunyi)', 'rahasia123')
            ->expectsQuestion('Ulangi password', 'beda456')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'salah@example.test']);
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.test']);

        $this->artisan('app:create-user', [
            '--nama' => 'Duplikat',
            '--email' => 'dup@example.test',
            '--role' => 'admin',
            '--password' => 'rahasia123',
        ])->assertFailed();
    }
}
