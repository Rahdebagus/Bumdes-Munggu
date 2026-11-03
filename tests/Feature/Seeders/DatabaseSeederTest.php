<?php

namespace Tests\Feature\Seeders;

use App\Domain\Reporting\ReportService;
use App\Models\MutasiStok;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** `php artisan db:seed` tanpa --class memuat modal awal BUMDes (ModalAwalBumdesSeeder), sama dengan database lokal. */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    private function configSeed(?string $periodeAwal = null): void
    {
        config([
            'seed.admin_password' => 'rahasia-admin-123',
            'seed.pimpinan_password' => 'rahasia-pimpinan-123',
            'seed.periode_awal' => $periodeAwal,
        ]);
    }

    public function test_db_seed_default_memuat_modal_awal_200_juta(): void
    {
        $this->configSeed();

        $this->seed();

        $this->assertSame(['abumdesmunggu@gmail.com', 'pbumdesmunggu@gmail.com'], User::orderBy('email')->pluck('email')->all());
        $this->assertSame(159, Produk::count());
        $this->assertSame(90, Produk::where('stok_terkini', '>', 0)->count());
        $this->assertSame(90, MutasiStok::where('jenis', 'opening')->count());
        $this->assertNotNull(PeriodeAkuntansi::where('tahun', 2026)->where('bulan', 11)->value('opening_locked_at'));

        $svc = app(ReportService::class);
        $neraca = $svc->neraca('2026-11-30', null, '2026-11-01');
        $this->assertSame('100000000.00', $neraca['kas_bank']);
        $this->assertSame('100000000.00', $neraca['persediaan']);
        $this->assertSame('200000000.00', $neraca['total_aset']);
        $this->assertSame('200000000.00', $neraca['modal']);

        // Pembagian persediaan per unit sama dengan database lokal.
        $unit = fn (string $kode) => UnitUsaha::where('kode_unit', $kode)->value('id');
        $this->assertSame('30526130.00', $svc->neraca('2026-11-30', $unit('UNIT-ATK'), '2026-11-01')['persediaan']);
        $this->assertSame('61085870.00', $svc->neraca('2026-11-30', $unit('UNIT-DAGANG'), '2026-11-01')['persediaan']);
        $this->assertSame('8388000.00', $svc->neraca('2026-11-30', $unit('UNIT-KONSUMSI'), '2026-11-01')['persediaan']);
        $this->assertSame('100000000.00', $svc->neraca('2026-11-30', $unit('UNIT-KREDIT'), '2026-11-01')['kas_bank']);

        $checks = collect($svc->reconciliation('2026-11-01', '2026-11-30', null, '2026-11-01'));
        $this->assertTrue($checks->every(fn ($c) => $c['match']), 'Semua rekonsiliasi harus cocok.');

        // Dijalankan ulang → tidak berubah.
        $this->seed();
        $this->assertSame('200000000.00', $svc->neraca('2026-11-30', null, '2026-11-01')['total_aset']);
        $this->assertSame(90, MutasiStok::count());
    }

    public function test_jalur_production_dengan_seed_periode_awal_sama(): void
    {
        $this->configSeed('2026-11');
        $this->app['env'] = 'production';

        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();

        $this->assertSame(1, PeriodeAkuntansi::count());
        $this->assertNotNull(PeriodeAkuntansi::where('tahun', 2026)->where('bulan', 11)->value('opening_locked_at'));
        $this->assertSame('200000000.00', app(ReportService::class)->neraca('2026-11-30', null, '2026-11-01')['total_aset']);
    }

    public function test_seed_periode_awal_berbeda_dibatalkan_tanpa_menulis(): void
    {
        $this->configSeed('2026-12');

        try {
            $this->seed();
            $this->fail('Seeder seharusnya menolak SEED_PERIODE_AWAL yang berbeda.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('SEED_PERIODE_AWAL', $e->getMessage());
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, PeriodeAkuntansi::count());
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
