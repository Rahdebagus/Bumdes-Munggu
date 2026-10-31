<?php

namespace Tests\Feature\Seeders;

use App\Domain\Reporting\ReportService;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** `php artisan db:seed` tanpa --class langsung memuat data BUMDes (ProduksiBumdesSeeder). */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_seed_default_memuat_data_bumdes(): void
    {
        config([
            'seed.admin_password' => 'rahasia-admin-123',
            'seed.pimpinan_password' => 'rahasia-pimpinan-123',
            'seed.periode_awal' => null,
        ]);

        $this->seed();

        $this->assertSame(['abumdesmunggu@gmail.com', 'pbumdesmunggu@gmail.com'], User::orderBy('email')->pluck('email')->all());
        $this->assertSame(159, Produk::count());
        $this->assertNotNull(PeriodeAkuntansi::where('tahun', 2026)->where('bulan', 7)->value('opening_locked_at'));
        $this->assertSame('1215728153.78', app(ReportService::class)->neraca('2026-07-31', null, '2026-07-01')['total_aset']);
    }

    public function test_tanpa_password_dibatalkan_tanpa_menulis_akun(): void
    {
        config(['seed.admin_password' => null, 'seed.pimpinan_password' => null]);

        try {
            $this->seed();
            $this->fail('Seeder seharusnya menolak tanpa password.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SEED_ADMIN_PASSWORD', $e->getMessage());
        }

        $this->assertSame(0, User::count());
    }
}
