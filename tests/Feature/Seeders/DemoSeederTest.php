<?php

namespace Tests\Feature\Seeders;

use App\Domain\Reporting\ReportService;
use App\Domain\Reporting\StatementService;
use App\Domain\Shared\Decimal;
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
        $this->assertContains('Modal Dana Desa', $ekuitasLabels);

        // Laba rugi: beban rinci + pajak usaha terpisah, bunga bank di pendapatan lain.
        $lr = $stmt->labaRugi($from, $to, null, $from);
        $this->assertContains('Beban Gaji Pegawai', array_column($lr['beban']['rows'], 'label'));
        $this->assertContains('Pajak Usaha', array_column($lr['pajak']['rows'], 'label'));
        $this->assertContains('Pendapatan Bunga Bank', array_column($lr['pendapatan_lain']['rows'], 'label'));

        // StatementService laba bersih == ReportService labaBersih (rekonsiliasi).
        $svcLaba = app(ReportService::class)->labaBersih($from, $to);
        $this->assertSame(0, Decimal::compare($lr['laba_bersih'], $svcLaba), 'laba rugi HTML == engine');

        // Laba Januari positif (demo sehat): 637.000.
        $this->assertSame(0, Decimal::compare($lr['laba_bersih'], '637000'), 'laba bersih Januari = 637.000');
    }
}
