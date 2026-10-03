<?php

namespace Tests\Feature\Reporting;

use App\Domain\Reporting\ReportService;
use App\Domain\Shared\Decimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Reporting\Concerns\BuildsFixtureA;
use Tests\TestCase;

/**
 * Fixture A (dokumen11): alur finansial lintas modul, cutover 1 Jan 2026, dua
 * unit (dagang 8jt, kredit 2jt), pajak/diskon nol, rata-rata bergerak. Semua
 * langkah dijalankan lewat Actions aplikasi — bukan menulis total ke tabel —
 * lalu laporan konsolidasi dibuktikan sama dengan angka expected dokumen.
 */
class FixtureATest extends TestCase
{
    use BuildsFixtureA;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFixtureA();
    }

    public function test_neraca_seimbang_dan_pos_pos_sesuai_dokumen(): void
    {
        $this->runFixture();
        $n = app(ReportService::class)->neraca(self::TO, cutoverFrom: self::FROM);

        $this->assertSame('7062000.00', $n['kas_bank']);
        $this->assertSame('250000.00', $n['piutang_usaha']);
        $this->assertSame('1100000.00', $n['piutang_pinjaman']);
        $this->assertSame('1000000.00', $n['persediaan']);
        $this->assertSame('1200000.00', $n['aset_tetap_bruto']);
        $this->assertSame('100000.00', $n['akumulasi_penyusutan']);
        $this->assertSame('1100000.00', $n['aset_tetap_neto']);
        $this->assertSame('10512000.00', $n['total_aset']);
        $this->assertSame('400000.00', $n['utang']);
        $this->assertSame('10000000.00', $n['modal']);
        $this->assertSame('112000.00', $n['laba_kumulatif']);
        $this->assertSame('10112000.00', $n['total_ekuitas']);
        $this->assertTrue($n['seimbang'], 'Neraca harus seimbang (aset = kewajiban + ekuitas).');
    }

    public function test_laba_rugi_dan_omzet_desa(): void
    {
        $this->runFixture();
        $svc = app(ReportService::class);
        $lr = $svc->labaRugi(self::FROM, self::TO);

        $this->assertSame('750000.00', $lr['penjualan_bersih']);
        $this->assertSame('500000.00', $lr['hpp']);
        $this->assertSame('250000.00', $lr['laba_kotor']);
        $this->assertSame('12000.00', $lr['pendapatan_kredit']);
        $this->assertSame('50000.00', $lr['beban_operasional']);
        $this->assertSame('100000.00', $lr['penyusutan']);
        $this->assertSame('112000.00', $lr['laba_bersih']);

        // Omzet desa = penjualan 750.000 + pendapatan kredit 12.000 = 762.000.
        // Tidak termasuk 200.000 pelunasan piutang atau 100.000 pokok angsuran.
        $this->assertSame('762000.00', $svc->omzetDesa(self::FROM, self::TO));
    }

    public function test_arus_kas_langsung(): void
    {
        $this->runFixture();
        $ak = app(ReportService::class)->arusKas(self::FROM, self::TO);

        $this->assertSame('-1738000.00', $ak['operasi']);
        $this->assertSame('-1200000.00', $ak['investasi']);
        $this->assertSame('0.00', $ak['pendanaan']);
        $this->assertSame('-2938000.00', $ak['arus_bersih']);
        $this->assertSame('10000000.00', $ak['kas_awal']);
        $this->assertSame('7062000.00', $ak['kas_akhir']);
    }

    public function test_perubahan_modal_dan_kredit(): void
    {
        $this->runFixture();
        $svc = app(ReportService::class);

        $pm = $svc->perubahanModal(self::FROM, self::TO);
        $this->assertSame('10000000.00', $pm['ekuitas_awal']);
        $this->assertSame('0.00', $pm['penambahan_modal']);
        $this->assertSame('0.00', $pm['pengurangan_modal']);
        $this->assertSame('112000.00', $pm['laba_periode']);
        $this->assertSame('10112000.00', $pm['ekuitas_akhir']);

        $kredit = $svc->kredit(self::TO);
        $this->assertSame('1100000.00', $kredit['total_sisa_pokok']);
        $this->assertCount(1, $kredit['rows']);
        $this->assertSame('1200000.00', $kredit['rows'][0]['pokok_awal']);
        $this->assertSame('100000.00', $kredit['rows'][0]['pokok_dibayar']);
    }

    public function test_rekonsiliasi_semua_cocok(): void
    {
        $this->runFixture();
        $checks = app(ReportService::class)->reconciliation(self::FROM, self::TO);

        foreach ($checks as $c) {
            $this->assertTrue(
                $c['match'],
                "Rekonsiliasi '{$c['key']}' gagal: expected {$c['expected']} ≠ actual {$c['actual']}."
            );
        }
        $this->assertNotEmpty($checks);
    }

    public function test_buku_besar_saldo_berjalan_konsisten(): void
    {
        $this->runFixture();
        $bb = app(ReportService::class)->bukuBesar(self::FROM, self::TO);

        $totalSaldo = '0';
        foreach ($bb as $akun) {
            if ($akun['entries'] !== []) {
                $last = end($akun['entries']);
                $this->assertSame($akun['saldo_akhir'], $last['saldo']);
            }
            $totalSaldo = Decimal::add($totalSaldo, $akun['saldo_akhir']);
        }

        // Σ saldo signed (debit−kredit) seluruh akun = 0 (buku berimbang).
        $this->assertSame('0.00', $totalSaldo);
    }
}
