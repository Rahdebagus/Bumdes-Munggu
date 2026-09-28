<?php

namespace Tests\Feature\Trading;

use App\Domain\Accounting\PostingService;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TradingPostingTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private PeriodeAkuntansi $periode;

    private UnitUsaha $unit;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        $this->periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->produk = Produk::factory()->create([
            'unit_usaha_id' => $this->unit->id,
            'stok_terkini' => 0,
            'harga_pokok_rata' => 0,
        ]);
    }

    private function akunId(string $key): int
    {
        return (int) Akun::where('system_key', $key)->value('id');
    }

    private function debit(Transaksi $trx, string $key): string
    {
        return Decimal::of((string) TransaksiDetail::where('transaksi_id', $trx->id)->where('akun_id', $this->akunId($key))->sum('debit'));
    }

    private function kredit(Transaksi $trx, string $key): string
    {
        return Decimal::of((string) TransaksiDetail::where('transaksi_id', $trx->id)->where('akun_id', $this->akunId($key))->sum('kredit'));
    }

    private function purchase(string $qty, string $harga, string $metode = 'tunai', string $tanggal = '2026-01-15'): Transaksi
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal,
            'unit_usaha_id' => $this->unit->id,
            'pihak_id' => null,
            'metode_pembayaran' => $metode,
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => $qty, 'harga' => $harga, 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Pembelian, $this->actor);

        return app(PostingService::class)->post($draft, 1, $this->actor, 'pur-'.$draft->id, 'h');
    }

    private function sale(string $qty, string $harga, string $metode = 'tunai', string $tanggal = '2026-01-16'): Transaksi
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal,
            'unit_usaha_id' => $this->unit->id,
            'pihak_id' => null,
            'metode_pembayaran' => $metode,
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => $qty, 'harga' => $harga, 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Penjualan, $this->actor);

        return app(PostingService::class)->post($draft, 1, $this->actor, 'sal-'.$draft->id, 'h');
    }

    public function test_purchase_increases_stock_and_moving_average(): void
    {
        $p1 = $this->purchase('10', '10000');

        $this->assertSame(TransaksiStatus::Posted, $p1->status);
        $this->produk->refresh();
        $this->assertSame('10.0000', $this->produk->stok_terkini);
        $this->assertSame('10000.000000', $this->produk->harga_pokok_rata);
        $this->assertSame('100000.00', $this->debit($p1, 'inventory'));
        $this->assertSame('100000.00', $this->kredit($p1, 'cash'));

        // Beli lagi 10 @ 14000 → rata-rata (100000+140000)/20 = 12000 (dokumen08).
        $this->purchase('10', '14000');
        $this->produk->refresh();
        $this->assertSame('20.0000', $this->produk->stok_terkini);
        $this->assertSame('12000.000000', $this->produk->harga_pokok_rata);
    }

    public function test_sale_reduces_stock_and_records_hpp(): void
    {
        $this->purchase('10', '10000');
        $this->purchase('10', '14000'); // avg 12000, saldo 20 nilai 240000

        $sale = $this->sale('5', '20000');

        $this->produk->refresh();
        $this->assertSame('15.0000', $this->produk->stok_terkini);       // 20 - 5
        $this->assertSame('12000.000000', $this->produk->harga_pokok_rata); // avg tetap
        // Jurnal penjualan: kas 100000 / penjualan 100000; HPP 60000 / persediaan 60000.
        $this->assertSame('100000.00', $this->debit($sale, 'cash'));
        $this->assertSame('100000.00', $this->kredit($sale, 'sales'));
        $this->assertSame('60000.00', $this->debit($sale, 'cogs'));
        $this->assertSame('60000.00', $this->kredit($sale, 'inventory'));
    }

    public function test_credit_sale_uses_receivable(): void
    {
        $this->purchase('10', '10000');
        $sale = $this->sale('5', '20000', 'kredit');

        $this->assertSame('100000.00', $this->debit($sale, 'trade_receivable'));
        $this->assertSame('0.00', $this->debit($sale, 'cash'));
    }

    public function test_insufficient_stock_is_rejected_and_rolled_back(): void
    {
        $this->purchase('5', '10000');

        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-16',
            'unit_usaha_id' => $this->unit->id,
            'pihak_id' => null,
            'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '10', 'harga' => '20000', 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Penjualan, $this->actor);

        try {
            app(PostingService::class)->post($draft, 1, $this->actor, 'sal-x', 'h');
            $this->fail('Seharusnya INSUFFICIENT_STOCK');
        } catch (DomainException $e) {
            $this->assertSame('INSUFFICIENT_STOCK', $e->domainCode);
        }

        $this->assertSame(TransaksiStatus::Draft, $draft->fresh()->status);
        $this->produk->refresh();
        $this->assertSame('5.0000', $this->produk->stok_terkini); // tidak berubah
    }

    public function test_backdated_stock_movement_rejected(): void
    {
        $this->purchase('10', '10000', 'tunai', '2026-01-20');

        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-10', // mundur dari mutasi terakhir (01-20)
            'unit_usaha_id' => $this->unit->id,
            'pihak_id' => null,
            'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '5', 'harga' => '10000', 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Pembelian, $this->actor);

        try {
            app(PostingService::class)->post($draft, 1, $this->actor, 'pur-back', 'h');
            $this->fail('Seharusnya STOCK_BACKDATE');
        } catch (DomainException $e) {
            $this->assertSame('STOCK_BACKDATE', $e->domainCode);
        }

        $this->assertSame(TransaksiStatus::Draft, $draft->fresh()->status);
    }
}
