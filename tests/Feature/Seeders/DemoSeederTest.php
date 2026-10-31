<?php

namespace Tests\Feature\Seeders;

use App\Domain\Reporting\ReportService;
use App\Domain\Reporting\StatementService;
use App\Domain\Shared\Decimal;
use App\Models\Akun;
use App\Models\MutasiStok;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_accounts_populate_and_reconcile(): void
    {
        $this->seed(DemoSeeder::class);

        // Akun demo admin & pimpinan tersedia untuk login/uji persetujuan.
        $this->assertDatabaseHas('users', ['email' => 'admin.demo@bumdes-munggu.test', 'role' => 'admin', 'status' => 'aktif']);
        $this->assertDatabaseHas('users', ['email' => 'pimpinan.demo@bumdes-munggu.test', 'role' => 'pimpinan', 'status' => 'aktif']);

        $stmt = app(StatementService::class);
        $from = '2026-01-01';
        $to = '2026-01-31';

        // Neraca seimbang dengan akun bernama (dana ekuitas) ikut.
        $n = $stmt->neraca($to, null, $from);
        $this->assertTrue($n['seimbang'], 'neraca Januari seimbang');
        $ekuitasLabels = array_column($n['ekuitas']['rows'], 'label');
        $this->assertContains('Dana Pendidikan', $ekuitasLabels);
        $this->assertContains('Modal Desa Munggu', $ekuitasLabels);

        // Laba rugi: beban rinci + pajak usaha terpisah, bunga bank di pendapatan lain.
        $lr = $stmt->labaRugi($from, $to, null, $from);
        $this->assertContains('Biaya Pegawai', array_column($lr['beban']['rows'], 'label'));
        $this->assertContains('Pajak', array_column($lr['pajak']['rows'], 'label'));
        $this->assertContains('Pendapatan Bunga Bank', array_column($lr['pendapatan_lain']['rows'], 'label'));

        // StatementService laba bersih == ReportService labaBersih (rekonsiliasi).
        $svcLaba = app(ReportService::class)->labaBersih($from, $to);
        $this->assertSame(0, Decimal::compare($lr['laba_bersih'], $svcLaba), 'laba rugi HTML == engine');

        // Angka latihan yang bisa dihitung manual (lihat docblock DemoSeeder).
        $this->assertSame(0, Decimal::compare($lr['laba_bersih'], '503000'), 'laba bersih Januari = 503.000');
        $this->assertSame(0, Decimal::compare($lr['pendapatan']['total'], '4763000'), 'penjualan 4.763.000');
        $this->assertSame(0, Decimal::compare($lr['hpp']['hpp'], '4132000'), 'HPP 4.132.000');
    }

    public function test_modal_50_juta_stok_20_sampai_50_dan_semua_rekonsiliasi_lulus(): void
    {
        $this->seed(DemoSeeder::class);

        $modal = (int) Akun::where('kode_akun', '3.1.01.00')->value('id');
        $net = app(ReportService::class)->accountNet(null, '2026-01-31', true)[$modal] ?? '0';
        $this->assertSame('-50000000.00', Decimal::of($net), 'Modal Desa Munggu = 50.000.000 (kredit)');

        // Saldo stok awal tiap produk latihan 20–50.
        foreach (DemoSeeder::STOK as $nama => $qty) {
            $this->assertGreaterThanOrEqual(20, $qty);
            $this->assertLessThanOrEqual(50, $qty);
            $awal = MutasiStok::whereHas('produk', fn ($q) => $q->where('nama_produk', $nama))->where('jenis', 'opening')->value('kuantitas_masuk');
            $this->assertSame((float) $qty, (float) $awal, "stok awal {$nama}");
        }

        $n = app(StatementService::class)->neraca('2026-01-31', null, '2026-01-01');
        $this->assertSame(0, Decimal::compare($n['total_aktiva'], '50403000'), 'total aktiva 50.403.000');
        $this->assertTrue($n['seimbang']);

        foreach (app(ReportService::class)->reconciliation('2026-01-01', '2026-01-31', null, '2026-01-01') as $c) {
            $this->assertTrue($c['match'], "rekonsiliasi {$c['key']}: {$c['expected']} vs {$c['actual']}");
        }
    }
}
