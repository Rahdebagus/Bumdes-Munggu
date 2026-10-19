<?php

namespace Tests\Feature\Console;

use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    /** Template yang benar-benar dibagikan ke pengelola — sekaligus menjaga template itu tetap valid. */
    private function template(string $nama): string
    {
        return base_path('docs/templates/'.$nama);
    }

    private function fixture(string $nama): string
    {
        return base_path('tests/Fixtures/opening-balance/'.$nama);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);

        User::create([
            'nama' => 'Admin Uji', 'email' => 'admin.uji@contoh.test',
            'role' => 'admin', 'status' => 'aktif', 'password' => bcrypt('rahasia12345'),
        ]);
    }

    public function test_impor_xlsx_membuat_periode_menyimpan_dan_mengunci(): void
    {
        $this->artisan('app:import-opening-balance', [
            'berkas' => $this->template('saldo-awal-mulai-cepat.xlsx'),
            '--periode' => '2026-10',
            '--kunci' => true,
        ])->assertSuccessful();

        $periode = PeriodeAkuntansi::where(['tahun' => 2026, 'bulan' => 10])->firstOrFail();
        $this->assertSame(5, SaldoAwalAkun::where('periode_id', $periode->id)->count());
        $this->assertNotNull($periode->opening_locked_at, 'harus terkunci');
    }

    public function test_impor_csv_juga_jalan(): void
    {
        $this->artisan('app:import-opening-balance', [
            'berkas' => $this->template('saldo-awal-mulai-cepat.csv'),
            '--periode' => '2026-10',
        ])->assertSuccessful();

        $this->assertSame(5, SaldoAwalAkun::count());
        $this->assertNull(PeriodeAkuntansi::first()->opening_locked_at, 'tanpa --kunci tetap draft');
    }

    public function test_berkas_dengan_akun_kontrol_gagal_dikunci_tanpa_rincian(): void
    {
        $this->artisan('app:import-opening-balance', [
            'berkas' => $this->fixture('akun-kontrol-tanpa-rincian.xlsx'),
            '--periode' => '2026-10',
            '--kunci' => true,
        ])->assertFailed();

        // Tersimpan sebagai draft, tetapi tidak terkunci.
        $this->assertSame(14, SaldoAwalAkun::count());
        $this->assertNull(PeriodeAkuntansi::first()->opening_locked_at);
    }

    public function test_berkas_tidak_ada_ditolak(): void
    {
        $this->artisan('app:import-opening-balance', [
            'berkas' => base_path('tests/Fixtures/opening-balance/tidak-ada.xlsx'),
            '--periode' => '2026-10',
        ])->assertFailed();

        $this->assertSame(0, SaldoAwalAkun::count());
    }

    public function test_periode_salah_format_ditolak_tanpa_menulis(): void
    {
        $this->artisan('app:import-opening-balance', [
            'berkas' => $this->template('saldo-awal-mulai-cepat.xlsx'),
            '--periode' => 'Oktober 2026',
        ])->assertFailed();

        $this->assertSame(0, SaldoAwalAkun::count());
        $this->assertSame(0, PeriodeAkuntansi::count());
    }
}
