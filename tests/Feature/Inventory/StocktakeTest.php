<?php

namespace Tests\Feature\Inventory;

use App\Domain\Accounting\PostingService;
use App\Domain\Inventory\StocktakeService;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\StokOpnameStatus;
use App\Enums\TransaksiJenis;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\StokOpname;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StocktakeTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $pimpinan;

    private PeriodeAkuntansi $periode;

    private UnitUsaha $unit;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        $this->pimpinan = User::factory()->pimpinan()->create();
        $this->periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->produk = Produk::factory()->create(['unit_usaha_id' => $this->unit->id, 'stok_terkini' => 0, 'harga_pokok_rata' => 0]);
        $this->purchase('10', '10000'); // stok 10 @ 10.000
    }

    private function purchase(string $qty, string $harga): void
    {
        $d = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-05', 'unit_usaha_id' => $this->unit->id, 'pihak_id' => null, 'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => $qty, 'harga' => $harga, 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Pembelian, $this->actor);
        app(PostingService::class)->post($d, 1, $this->actor, 'pur-'.$d->id, 'h');
    }

    private function startOpname(string $fisik): StokOpname
    {
        return app(StocktakeService::class)->start([
            'tanggal' => '2026-01-20', 'periode_id' => $this->periode->id, 'unit_usaha_id' => $this->unit->id,
            'items' => [['produk_id' => $this->produk->id, 'stok_fisik' => $fisik]],
        ], $this->actor);
    }

    private function advanceToApproved(StokOpname $o): void
    {
        $svc = app(StocktakeService::class);
        $svc->complete($o);
        $svc->submit($o);
        $svc->approve($o, $this->pimpinan);
    }

    private function postOpname(StokOpname $o): Transaksi
    {
        return app(StocktakeService::class)->post($o, $this->actor, 'op-'.$o->id, 'h');
    }

    private function amount(Transaksi $trx, string $key, string $side): string
    {
        return Decimal::of((string) TransaksiDetail::where('transaksi_id', $trx->id)
            ->where('akun_id', (int) Akun::where('system_key', $key)->value('id'))->sum($side));
    }

    public function test_opname_loss_posts_adjustment(): void
    {
        $o = $this->startOpname('8'); // selisih -2 → 20.000
        $this->advanceToApproved($o);
        $trx = $this->postOpname($o);

        $this->assertSame('8.0000', $this->produk->fresh()->stok_terkini);
        $this->assertSame('20000.00', $this->amount($trx, 'inventory_loss', 'debit'));
        $this->assertSame('20000.00', $this->amount($trx, 'inventory', 'kredit'));
        $this->assertSame(StokOpnameStatus::Posted, $o->fresh()->status);
    }

    public function test_opname_gain_posts_adjustment(): void
    {
        $o = $this->startOpname('12'); // selisih +2 → 20.000
        $this->advanceToApproved($o);
        $trx = $this->postOpname($o);

        $this->assertSame('12.0000', $this->produk->fresh()->stok_terkini);
        $this->assertSame('20000.00', $this->amount($trx, 'inventory', 'debit'));
        $this->assertSame('20000.00', $this->amount($trx, 'inventory_gain', 'kredit'));
    }

    public function test_stale_rejected_when_stock_changes_after_count(): void
    {
        $o = $this->startOpname('8');
        $this->advanceToApproved($o);

        // Mutasi baru setelah hitung → stock_version berubah.
        $this->purchase('5', '10000');

        try {
            $this->postOpname($o);
            $this->fail('Seharusnya STALE_SNAPSHOT');
        } catch (DomainException $e) {
            $this->assertSame('STALE_SNAPSHOT', $e->domainCode);
        }

        $this->assertSame(StokOpnameStatus::Disetujui, $o->fresh()->status);
    }

    public function test_cannot_post_before_approved(): void
    {
        $o = $this->startOpname('8');

        $this->expectException(DomainException::class);
        $this->postOpname($o);
    }

    public function test_request_revision_resets_to_draft(): void
    {
        $o = $this->startOpname('8');
        $svc = app(StocktakeService::class);
        $svc->complete($o);
        $svc->submit($o);
        $svc->approve($o, $this->pimpinan);

        $svc->requestRevision($o);

        $o->refresh();
        $this->assertSame(StokOpnameStatus::Draft, $o->status);
        $this->assertNull($o->approved_by);
        $this->assertSame(2, (int) $o->revision);
    }
}
