<?php

namespace Tests\Feature\Livewire;

use App\Domain\Accounting\PostingService;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Livewire\Accounting\Transactions\Show;
use App\Livewire\Trading\Create;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TradingUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private UnitUsaha $unit;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->produk = Produk::factory()->create(['unit_usaha_id' => $this->unit->id, 'stok_terkini' => 0, 'harga_pokok_rata' => 0]);

        // Stok awal lewat pembelian terposting.
        $beli = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-05', 'unit_usaha_id' => $this->unit->id, 'pihak_id' => null,
            'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '10', 'harga' => '10000', 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Pembelian, $this->admin);
        app(PostingService::class)->post($beli, 1, $this->admin, 'pur-seed', 'h');
    }

    public function test_admin_creates_sale_draft_then_posts_from_show(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Create::class, ['jenis' => 'penjualan'])
            ->set('tanggal', '2026-01-15')
            ->set('unit_usaha_id', $this->unit->id)
            ->set('metode_pembayaran', 'tunai')
            ->set('akun_kas_id', (int) Akun::where('system_key', 'cash')->value('id'))
            ->set('items', [['produk_id' => $this->produk->id, 'kuantitas' => '5', 'harga' => '20000', 'diskon' => '0', 'pajak' => '0']])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $sale = Transaksi::where('jenis_transaksi', 'penjualan')->firstOrFail();
        $this->assertSame(TransaksiStatus::Draft, $sale->status);

        Livewire::actingAs($this->admin)
            ->test(Show::class, ['transaksi' => $sale])
            ->call('post')
            ->assertHasNoErrors();

        $this->assertSame(TransaksiStatus::Posted, $sale->fresh()->status);
        $this->assertSame('5.0000', $this->produk->fresh()->stok_terkini); // 10 - 5
    }

    public function test_pimpinan_cannot_create_sale(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();

        Livewire::actingAs($pimpinan)
            ->test(Create::class, ['jenis' => 'penjualan'])
            ->set('tanggal', '2026-01-15')
            ->set('unit_usaha_id', $this->unit->id)
            ->set('metode_pembayaran', 'tunai')
            ->set('akun_kas_id', (int) Akun::where('system_key', 'cash')->value('id'))
            ->set('items', [['produk_id' => $this->produk->id, 'kuantitas' => '5', 'harga' => '20000', 'diskon' => '0', 'pajak' => '0']])
            ->call('save')
            ->assertForbidden();
    }

    public function test_pimpinan_cannot_post_from_show(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();
        $sale = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-15', 'unit_usaha_id' => $this->unit->id, 'pihak_id' => null,
            'metode_pembayaran' => 'tunai',
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '5', 'harga' => '20000', 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Penjualan, $this->admin);

        Livewire::actingAs($pimpinan)
            ->test(Show::class, ['transaksi' => $sale])
            ->call('post')
            ->assertForbidden();

        $this->assertSame(TransaksiStatus::Draft, $sale->fresh()->status);
    }
}
