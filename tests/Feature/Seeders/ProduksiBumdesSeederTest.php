<?php

namespace Tests\Feature\Seeders;

use App\Domain\Reporting\ReportService;
use App\Enums\SaldoRincianJenis;
use App\Models\AsetTetap;
use App\Models\MutasiStok;
use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Produk;
use App\Models\SaldoAwalAkun;
use App\Models\SaldoAwalRincian;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\ProduksiBumdesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * ProduksiBumdesSeeder: neraca Excel 30 Juni 2026 → saldo awal terkunci Juli
 * 2026, angka sama persis dengan laporan keuangan BUMDes dan seluruh
 * rekonsiliasi (stok/piutang/pinjaman/aset) cocok.
 */
class ProduksiBumdesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'seed.admin_email' => 'admin@bumdesmunggu.id',
            'seed.admin_password' => 'rahasia-admin-123',
            'seed.pimpinan_email' => 'pimpinan@bumdesmunggu.id',
            'seed.pimpinan_password' => 'rahasia-pimpinan-123',
            'seed.periode_awal' => null,
        ]);
    }

    public function test_neraca_juni_2026_menjadi_saldo_awal_juli_terkunci(): void
    {
        $this->seed();

        $periode = PeriodeAkuntansi::where('tahun', 2026)->where('bulan', 7)->firstOrFail();
        $this->assertNotNull($periode->opening_locked_at);

        $neraca = app(ReportService::class)->neraca('2026-07-31', null, '2026-07-01');
        $this->assertSame('1215728153.78', $neraca['total_aset']);
        $this->assertSame('1215728153.78', $neraca['total_kewajiban_ekuitas']);
        $this->assertSame('260897426.78', $neraca['kas_bank']);
        $this->assertSame('67765827.00', $neraca['persediaan']);
        $this->assertSame('752016100.00', $neraca['piutang_usaha']);
        $this->assertSame('126123000.00', $neraca['piutang_pinjaman']);
        $this->assertSame('8925800.00', $neraca['aset_tetap_neto']);

        foreach (app(ReportService::class)->reconciliation('2026-07-01', '2026-07-31', null, '2026-07-01') as $c) {
            $this->assertTrue($c['match'], "Rekonsiliasi {$c['label']}: {$c['expected']} ≠ {$c['actual']}");
        }
    }

    public function test_rincian_master_dan_stok_awal_terbentuk(): void
    {
        $this->seed();

        $this->assertSame(159, Produk::count());
        $this->assertSame(91, MutasiStok::where('jenis', 'opening')->whereNotNull('opening_rincian_id')->count());
        $this->assertSame('67765827.00', (string) MutasiStok::sum('saldo_nilai').'');
        $this->assertSame(228, SaldoAwalRincian::where('jenis', SaldoRincianJenis::PiutangUsaha->value)->count());
        $this->assertSame(32, SaldoAwalRincian::where('jenis', SaldoRincianJenis::Pinjaman->value)->count());
        $this->assertSame(8, Pihak::where('jenis_pihak', 'pelanggan')->count());
        $this->assertSame(32, Pihak::where('jenis_pihak', 'peminjam')->count());
        $this->assertSame(7, AsetTetap::count());
        $this->assertSame(4, AsetTetap::whereNotNull('opening_rincian_id')->count());

        // Lot ganda di Excel digabung: Stop Map Folio 245 unit senilai 367.500.
        $produk = Produk::where('nama_produk', 'Stop Map Folio')->firstOrFail();
        $this->assertSame('245.0000', (string) $produk->stok_terkini);
        $this->assertSame('1500.000000', (string) $produk->harga_pokok_rata);
    }

    public function test_aman_dijalankan_ulang(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, PeriodeAkuntansi::whereNotNull('opening_locked_at')->count());
        $this->assertSame(91, MutasiStok::count());
        $this->assertSame(7, AsetTetap::count());
    }

    public function test_menolak_database_yang_sudah_berisi_saldo_awal(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);
        $lain = PeriodeAkuntansi::create(['tahun' => 2026, 'bulan' => 1, 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-01-31', 'status' => 'terbuka', 'revision' => 0]);
        SaldoAwalAkun::create(['periode_id' => $lain->id, 'akun_id' => \App\Models\Akun::where('kode_akun', '1.1.01.01')->value('id'), 'saldo_debit' => 1, 'saldo_kredit' => 0, 'created_by' => \App\Models\User::value('id')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('database baru');

        $this->seed(ProduksiBumdesSeeder::class);
    }
}
