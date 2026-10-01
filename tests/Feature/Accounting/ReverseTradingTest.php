<?php

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\ReverseTransaction;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\Cash\SettlementQuery;
use App\Domain\Shared\DomainException;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReverseTradingTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private UnitUsaha $unit;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->produk = Produk::factory()->create(['unit_usaha_id' => $this->unit->id, 'stok_terkini' => 0, 'harga_pokok_rata' => 0]);
    }

    private function cash(): int
    {
        return (int) Akun::where('system_key', 'cash')->value('id');
    }

    private function purchase(string $qty, string $harga, string $tanggal = '2026-01-05'): Transaksi
    {
        $d = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal, 'unit_usaha_id' => $this->unit->id, 'pihak_id' => null, 'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => $qty, 'harga' => $harga, 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Pembelian, $this->actor);

        return app(PostingService::class)->post($d, 1, $this->actor, 'pur-'.$d->id, 'h');
    }

    private function sale(string $qty, string $harga, string $metode = 'tunai', string $tanggal = '2026-01-10'): Transaksi
    {
        $d = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal, 'unit_usaha_id' => $this->unit->id, 'pihak_id' => null, 'metode_pembayaran' => $metode,
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => $qty, 'harga' => $harga, 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Penjualan, $this->actor);

        return app(PostingService::class)->post($d, 1, $this->actor, 'sal-'.$d->id, 'h');
    }

    private function reverse(Transaksi $trx): Transaksi
    {
        return app(ReverseTransaction::class)->reverse($trx, $trx->tanggal->toDateString(), 'koreksi', $this->actor, 'rev-'.$trx->id, 'h');
    }

    public function test_reverse_sale_restores_stock(): void
    {
        $this->purchase('10', '10000');
        $sale = $this->sale('5', '20000');
        $this->assertSame('5.0000', $this->produk->fresh()->stok_terkini);

        $reversal = $this->reverse($sale);

        $this->assertSame($sale->id, $reversal->reversal_of_id);
        $this->assertSame(TransaksiStatus::Reversed, $sale->fresh()->status);
        $this->assertSame('10.0000', $this->produk->fresh()->stok_terkini); // stok kembali
    }

    public function test_reverse_rejected_when_newer_stock_movement_exists(): void
    {
        $this->purchase('10', '10000');
        $saleA = $this->sale('3', '20000', 'tunai', '2026-01-10');
        $this->sale('2', '20000', 'tunai', '2026-01-11'); // lebih baru

        try {
            $this->reverse($saleA);
            $this->fail('Seharusnya REVERSE_DEPENDENCY');
        } catch (DomainException $e) {
            $this->assertSame('REVERSE_DEPENDENCY', $e->domainCode);
        }

        $this->assertSame(TransaksiStatus::Posted, $saleA->fresh()->status);
    }

    public function test_reverse_credit_sale_rejected_when_settled(): void
    {
        $this->purchase('10', '10000');
        $sale = $this->sale('5', '20000', 'kredit');

        $pelunasan = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-20', 'unit_usaha_id' => $this->unit->id,
            'kategori_kode' => 'pelunasan_piutang', 'akun_kas_id' => $this->cash(),
            'allocations' => [['invoice_transaksi_id' => $sale->id, 'nominal' => '100000']],
        ], $this->actor);
        app(PostingService::class)->post($pelunasan, 1, $this->actor, 'pl-1', 'h');

        try {
            $this->reverse($sale);
            $this->fail('Seharusnya REVERSE_DEPENDENCY (pelunasan dulu)');
        } catch (DomainException $e) {
            $this->assertSame('REVERSE_DEPENDENCY', $e->domainCode);
        }
    }

    public function test_reverse_settlement_restores_outstanding(): void
    {
        $this->purchase('10', '10000');
        $sale = $this->sale('5', '20000', 'kredit');

        $pelunasan = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-20', 'unit_usaha_id' => $this->unit->id,
            'kategori_kode' => 'pelunasan_piutang', 'akun_kas_id' => $this->cash(),
            'allocations' => [['invoice_transaksi_id' => $sale->id, 'nominal' => '100000']],
        ], $this->actor);
        $posted = app(PostingService::class)->post($pelunasan, 1, $this->actor, 'pl-2', 'h');
        $this->assertSame('0.00', app(SettlementQuery::class)->invoiceOutstanding($sale->id));

        $this->reverse($posted);

        $this->assertSame(TransaksiStatus::Reversed, $posted->fresh()->status);
        $this->assertSame('100000.00', app(SettlementQuery::class)->invoiceOutstanding($sale->id));
    }
}
