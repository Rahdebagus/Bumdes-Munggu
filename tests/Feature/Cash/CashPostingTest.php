<?php

namespace Tests\Feature\Cash;

use App\Domain\Accounting\PostingService;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\Cash\SettlementQuery;
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

class CashPostingTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private PeriodeAkuntansi $periode;

    private UnitUsaha $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        $this->periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
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

    private function postedCreditSale(): Transaksi
    {
        $produk = Produk::factory()->create(['unit_usaha_id' => $this->unit->id, 'stok_terkini' => 0, 'harga_pokok_rata' => 0]);

        $beli = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-10', 'unit_usaha_id' => $this->unit->id, 'pihak_id' => null,
            'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $produk->id, 'kuantitas' => '10', 'harga' => '10000', 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Pembelian, $this->actor);
        app(PostingService::class)->post($beli, 1, $this->actor, 'pur-'.$beli->id, 'h');

        $jual = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-12', 'unit_usaha_id' => $this->unit->id, 'pihak_id' => null,
            'metode_pembayaran' => 'kredit',
            'items' => [['produk_id' => $produk->id, 'kuantitas' => '10', 'harga' => '10000', 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Penjualan, $this->actor);

        return app(PostingService::class)->post($jual, 1, $this->actor, 'sal-'.$jual->id, 'h');
    }

    public function test_cash_income_jasa(): void
    {
        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-15', 'unit_usaha_id' => $this->unit->id,
            'kategori_kode' => 'jasa', 'akun_kas_id' => $this->akunId('cash'),
            'akun_lawan_id' => $this->akunId('sales'), 'nominal' => '500000',
        ], $this->actor);

        $posted = app(PostingService::class)->post($draft, 1, $this->actor, 'ks-1', 'h');

        $this->assertSame(TransaksiStatus::Posted, $posted->status);
        $this->assertSame('500000.00', $this->debit($posted, 'cash'));
        $this->assertSame('500000.00', $this->kredit($posted, 'sales'));
    }

    public function test_cash_expense_beban(): void
    {
        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-15', 'unit_usaha_id' => $this->unit->id,
            'kategori_kode' => 'beban', 'akun_kas_id' => $this->akunId('cash'),
            'akun_lawan_id' => $this->akunId('operating_expense'), 'nominal' => '200000',
        ], $this->actor);

        $posted = app(PostingService::class)->post($draft, 1, $this->actor, 'ks-2', 'h');

        $this->assertSame('200000.00', $this->debit($posted, 'operating_expense'));
        $this->assertSame('200000.00', $this->kredit($posted, 'cash'));
    }

    public function test_settlement_reduces_receivable_without_new_revenue(): void
    {
        $sale = $this->postedCreditSale();
        $this->assertSame('100000.00', app(SettlementQuery::class)->invoiceOutstanding($sale->id));

        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-20', 'unit_usaha_id' => $this->unit->id, 'pihak_id' => $sale->pihak_id,
            'kategori_kode' => 'pelunasan_piutang', 'akun_kas_id' => $this->akunId('cash'),
            'allocations' => [['invoice_transaksi_id' => $sale->id, 'nominal' => '100000']],
        ], $this->actor);

        $posted = app(PostingService::class)->post($draft, 1, $this->actor, 'ks-3', 'h');

        // Kas naik, piutang turun, TANPA pendapatan baru.
        $this->assertSame('100000.00', $this->debit($posted, 'cash'));
        $this->assertSame('100000.00', $this->kredit($posted, 'trade_receivable'));
        $this->assertSame('0.00', $this->kredit($posted, 'sales'));
        $this->assertSame('0.00', app(SettlementQuery::class)->invoiceOutstanding($sale->id));
    }

    public function test_overpayment_rejected(): void
    {
        $sale = $this->postedCreditSale();

        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-20', 'unit_usaha_id' => $this->unit->id, 'pihak_id' => $sale->pihak_id,
            'kategori_kode' => 'pelunasan_piutang', 'akun_kas_id' => $this->akunId('cash'),
            'allocations' => [['invoice_transaksi_id' => $sale->id, 'nominal' => '150000']], // > 100000 outstanding
        ], $this->actor);

        try {
            app(PostingService::class)->post($draft, 1, $this->actor, 'ks-4', 'h');
            $this->fail('Seharusnya OVERPAYMENT');
        } catch (DomainException $e) {
            $this->assertSame('OVERPAYMENT', $e->domainCode);
        }

        $this->assertSame(TransaksiStatus::Draft, $draft->fresh()->status);
        $this->assertSame('100000.00', app(SettlementQuery::class)->invoiceOutstanding($sale->id));
    }
}
