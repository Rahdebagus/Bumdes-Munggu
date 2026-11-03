<?php

namespace Tests\Feature\Reporting;

use App\Domain\Accounting\PostingService;
use App\Domain\Reporting\ReportService;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\TransaksiJenis;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjualan produk berkategori (akun 4.1.x selain 'sales') ikut omzet & laba
 * rugi; pajak keluaran tidak memblokir rekonsiliasi utang dagang.
 */
class CategorySalesReportTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-01-01';

    private const TO = '2026-01-31';

    private User $actor;

    private UnitUsaha $unit;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-ATK')->firstOrFail();
        $this->produk = Produk::factory()->create([
            'unit_usaha_id' => $this->unit->id,
            'kategori' => 'atk',
            'stok_terkini' => 0,
            'harga_pokok_rata' => 0,
        ]);

        $this->trade(TransaksiJenis::Pembelian, '2026-01-10', '10', '19000', '0');
        // 10 × 21.000 + pajak 2.000 → Kas 212.000 / Penjualan ATK 210.000 / Utang Pajak 2.000.
        $this->trade(TransaksiJenis::Penjualan, '2026-01-15', '10', '21000', '2000');
    }

    private function trade(TransaksiJenis $jenis, string $tanggal, string $qty, string $harga, string $pajak): void
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal,
            'unit_usaha_id' => $this->unit->id,
            'pihak_id' => null,
            'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => $qty, 'harga' => $harga, 'diskon' => '0', 'pajak' => $pajak]],
        ], $jenis, $this->actor);

        app(PostingService::class)->post($draft, 1, $this->actor, 'trd-'.$draft->id, 'h');
    }

    public function test_category_sales_count_in_omzet_and_laba_rugi(): void
    {
        $svc = app(ReportService::class);

        $omzet = $svc->omzetDesaBreakdown(self::FROM, self::TO);
        $this->assertSame('210000.00', $omzet['omzet_dagang']);
        $this->assertSame('210000.00', $omzet['total_omzet']);
        $this->assertSame('210000.00', $svc->omzetDesa(self::FROM, self::TO));

        $lr = $svc->labaRugi(self::FROM, self::TO);
        $this->assertSame('210000.00', $lr['penjualan_bersih']);
        $this->assertSame('190000.00', $lr['hpp']);
        $this->assertSame('20000.00', $lr['laba_kotor']);
        $this->assertSame('210000.00', $svc->labaRugi(self::FROM, self::TO, $this->unit->id)['penjualan_bersih']);
    }

    public function test_tax_payable_does_not_break_trade_payable_reconciliation(): void
    {
        $checks = collect(app(ReportService::class)->reconciliation(self::FROM, self::TO))->keyBy('key');

        $this->assertSame('0.00', $checks['utang_usaha']['actual']);
        $this->assertTrue($checks['utang_usaha']['match']);
        $this->assertTrue($checks->every(fn ($c) => $c['match']), 'Semua rekonsiliasi harus cocok.');
    }
}
