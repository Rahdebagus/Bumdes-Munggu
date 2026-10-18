<?php

namespace Tests\Feature\Seeders;

use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Konfigurasi .env yang lengkap & valid untuk bootstrap produksi. */
    private function setConfig(array $overrides = []): void
    {
        config(array_merge([
            'seed.admin_nama' => 'Administrator BUMDes',
            'seed.admin_email' => 'admin@bumdesmunggu.id',
            'seed.admin_password' => 'rahasia-admin-123',
            'seed.pimpinan_nama' => 'Pimpinan BUMDes',
            'seed.pimpinan_email' => 'pimpinan@bumdesmunggu.id',
            'seed.pimpinan_password' => 'rahasia-pimpinan-123',
            'seed.periode_awal' => '2026-01',
        ], $overrides));
    }

    public function test_bootstraps_master_data_login_accounts_and_period(): void
    {
        $this->setConfig();
        $this->seed(ProductionSeeder::class);

        // Master data lengkap (unit + chart of accounts dengan seluruh system_key).
        $this->assertSame(2, UnitUsaha::count());
        $this->assertSame(32, Akun::count());
        foreach (config('accounting.system_keys') as $key) {
            $this->assertDatabaseHas('akun', ['system_key' => $key, 'is_active' => true]);
        }

        // Akun login dari config, ter-hash, aktif, dengan role yang benar.
        $this->assertSame(2, User::count());
        $admin = User::where('email', 'admin@bumdesmunggu.id')->firstOrFail();
        $this->assertSame('admin', $admin->role->value);
        $this->assertSame('aktif', $admin->status->value);
        $this->assertTrue(Hash::check('rahasia-admin-123', $admin->password));
        $this->assertNotSame('rahasia-admin-123', $admin->password, 'password harus ter-hash');

        $pimpinan = User::where('email', 'pimpinan@bumdesmunggu.id')->firstOrFail();
        $this->assertSame('pimpinan', $pimpinan->role->value);
        $this->assertTrue(Hash::check('rahasia-pimpinan-123', $pimpinan->password));

        // Periode go-live terbuka, tanggal mulai/selesai sesuai bulan.
        $periode = PeriodeAkuntansi::where(['tahun' => 2026, 'bulan' => 1])->firstOrFail();
        $this->assertSame('terbuka', $periode->status->value);
        $this->assertSame('2026-01-01', $periode->tanggal_mulai->toDateString());
        $this->assertSame('2026-01-31', $periode->tanggal_selesai->toDateString());
        $this->assertNull($periode->opening_locked_at, 'saldo awal dikunci pengelola, bukan seeder');

        // Tidak ada data operasional fiktif.
        $this->assertSame(0, Pihak::count());
        $this->assertSame(0, Produk::count());
        $this->assertSame(0, Transaksi::count());
    }

    public function test_is_idempotent_and_does_not_modify_existing_accounts(): void
    {
        $this->setConfig();
        $this->seed(ProductionSeeder::class);

        $hashBefore = User::where('email', 'admin@bumdesmunggu.id')->value('password');

        // Password di .env diganti lalu seeder diulang — akun lama tidak berubah.
        $this->setConfig(['seed.admin_password' => 'password-baru-999']);
        $this->seed(ProductionSeeder::class);

        $this->assertSame(2, UnitUsaha::count());
        $this->assertSame(32, Akun::count());
        $this->assertSame(2, User::count());
        $this->assertSame(1, PeriodeAkuntansi::count());
        $this->assertSame($hashBefore, User::where('email', 'admin@bumdesmunggu.id')->value('password'), 'password tidak diubah');
    }

    public function test_skips_period_when_not_configured(): void
    {
        $this->setConfig(['seed.periode_awal' => null]);
        $this->seed(ProductionSeeder::class);

        $this->assertSame(0, PeriodeAkuntansi::count(), 'periode dibuat manual lewat menu Periode Akuntansi');
        $this->assertSame(32, Akun::count(), 'master data tetap dibuat');
        $this->assertSame(2, User::count());
    }

    public function test_halts_before_writing_anything_when_password_missing(): void
    {
        $this->setConfig(['seed.pimpinan_password' => null]);

        try {
            $this->seed(ProductionSeeder::class);
            $this->fail('Seharusnya berhenti saat password pimpinan belum diatur.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('pimpinan@bumdesmunggu.id', $e->getMessage());
        }

        // Preflight: tidak ada master data/akun separuh terisi.
        $this->assertSame(0, Akun::count());
        $this->assertSame(0, UnitUsaha::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, PeriodeAkuntansi::count());
    }

    public function test_halts_on_invalid_period_format(): void
    {
        $this->setConfig(['seed.periode_awal' => 'Januari 2026']);

        try {
            $this->seed(ProductionSeeder::class);
            $this->fail('Seharusnya berhenti saat SEED_PERIODE_AWAL tidak valid.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SEED_PERIODE_AWAL', $e->getMessage());
        }

        $this->assertSame(0, Akun::count());
        $this->assertSame(0, User::count());
    }

    public function test_halts_when_admin_and_pimpinan_share_email(): void
    {
        $this->setConfig(['seed.pimpinan_email' => 'admin@bumdesmunggu.id']);

        try {
            $this->seed(ProductionSeeder::class);
            $this->fail('Seharusnya berhenti saat email admin & pimpinan sama.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak boleh sama', $e->getMessage());
        }

        $this->assertSame(0, User::count());
    }
}
