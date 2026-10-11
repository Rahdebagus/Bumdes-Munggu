<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    private function setPasswords(?string $admin = 'rahasia-admin-123', ?string $pimpinan = 'rahasia-pimpinan-123'): void
    {
        config(['seed.admin_password' => $admin, 'seed.pimpinan_password' => $pimpinan]);
    }

    public function test_creates_both_accounts_hashed_with_correct_roles(): void
    {
        $this->setPasswords();
        $this->seed(UserSeeder::class);

        $this->assertSame(2, User::count());

        $admin = User::where('email', 'admin@bumdes-munggu.test')->firstOrFail();
        $this->assertSame('admin', $admin->role->value);
        $this->assertSame('aktif', $admin->status->value);
        $this->assertTrue(Hash::check('rahasia-admin-123', $admin->password));
        $this->assertNotSame('rahasia-admin-123', $admin->password, 'password harus ter-hash');

        $pimpinan = User::where('email', 'pimpinan@bumdes-munggu.test')->firstOrFail();
        $this->assertSame('pimpinan', $pimpinan->role->value);
        $this->assertSame('aktif', $pimpinan->status->value);
        $this->assertTrue(Hash::check('rahasia-pimpinan-123', $pimpinan->password));
    }

    public function test_is_idempotent_and_does_not_modify_existing(): void
    {
        $this->setPasswords();
        $this->seed(UserSeeder::class);

        $adminHashBefore = User::where('email', 'admin@bumdes-munggu.test')->value('password');

        // Ubah config password, jalankan lagi — akun lama tidak boleh berubah.
        $this->setPasswords('password-baru-999', 'password-baru-888');
        $this->seed(UserSeeder::class);

        $this->assertSame(2, User::count(), 'tidak ada duplikasi');
        $this->assertSame($adminHashBefore, User::where('email', 'admin@bumdes-munggu.test')->value('password'), 'password tidak diubah');
    }

    public function test_reports_conflict_when_email_has_different_role(): void
    {
        $this->setPasswords();

        $existing = User::create([
            'nama' => 'Akun Lama',
            'email' => 'admin@bumdes-munggu.test',
            'role' => 'pimpinan',
            'status' => 'aktif',
            'password' => Hash::make('password-lama-123'),
        ]);

        $this->seed(UserSeeder::class);

        $existing->refresh();
        $this->assertSame('pimpinan', $existing->role->value, 'role tidak diubah diam-diam');
        $this->assertTrue(Hash::check('password-lama-123', $existing->password), 'password tidak diubah');

        // Akun pimpinan tetap dibuat normal.
        $this->assertDatabaseHas('users', ['email' => 'pimpinan@bumdes-munggu.test', 'role' => 'pimpinan']);
    }

    public function test_halts_when_password_not_configured_without_creating_any(): void
    {
        $this->setPasswords('rahasia-admin-123', null);

        try {
            $this->seed(UserSeeder::class);
            $this->fail('Seharusnya berhenti saat password pimpinan belum diatur.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('pimpinan@bumdes-munggu.test', $e->getMessage());
        }

        $this->assertSame(0, User::count(), 'tidak ada akun dibuat saat password belum lengkap');
    }
}
