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
use App\Models\Pihak;
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

    /** @return array<string, mixed> */
    private function saleForm(array $override = []): array
    {
        return array_merge([
            'tanggal' => '2026-01-15',
            'unit_usaha_id' => $this->unit->id,
            'pihak_id' => null,
            'metode_pembayaran' => 'tunai',
            'akun_kas_id' => (int) Akun::where('system_key', 'cash')->value('id'),
            'jatuh_tempo' => null,
            'keterangan' => null,
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '5', 'harga' => '20000', 'diskon' => '0', 'pajak' => '0']],
        ], $override);
    }

    public function test_admin_creates_sale_draft_then_posts_from_show(): void
    {
        $t = Livewire::actingAs($this->admin)
            ->test(Create::class, ['jenis' => 'penjualan'])
            ->call('save', $this->saleForm(), false);

        $res = $t->effects['returns'][0];
        $this->assertTrue($res['ok']);
        $this->assertFalse($res['posted']);
        $this->assertMatchesRegularExpression('/^SL-202601-\d{4}$/', $res['nomor']);

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
            ->call('save', $this->saleForm())
            ->assertForbidden();
    }

    public function test_simpan_dan_posting_langsung_mengurangi_stok_dan_form_siap_lagi(): void
    {
        $res = Livewire::actingAs($this->admin)
            ->test(Create::class, ['jenis' => 'penjualan'])
            ->call('save', $this->saleForm(['items' => [['produk_id' => $this->produk->id, 'kuantitas' => '3', 'harga' => '20000']]]))
            ->effects['returns'][0];

        $this->assertTrue($res['ok']);
        $this->assertTrue($res['posted']);
        $this->assertNull($res['warning']);
        $this->assertEquals(7, $res['stok'][$this->produk->id]); // 10 - 3, dikirim balik ke form
        $this->assertSame(TransaksiStatus::Posted, Transaksi::findOrFail($res['id'])->status);
    }

    public function test_kredit_wajib_pelanggan_dan_tunai_wajib_akun_kas(): void
    {
        $t = Livewire::actingAs($this->admin)->test(Create::class, ['jenis' => 'penjualan']);

        $kredit = $t->call('save', $this->saleForm(['metode_pembayaran' => 'kredit', 'akun_kas_id' => null]))->effects['returns'][0];
        $this->assertFalse($kredit['ok']);
        $this->assertArrayHasKey('pihak_id', $kredit['errors']);

        $tunai = $t->call('save', $this->saleForm(['akun_kas_id' => null]))->effects['returns'][0];
        $this->assertFalse($tunai['ok']);
        $this->assertArrayHasKey('akun_kas_id', $tunai['errors']);

        $tanpaProduk = $t->call('save', $this->saleForm(['items' => [['produk_id' => null, 'kuantitas' => '0', 'harga' => '0']]]))->effects['returns'][0];
        $this->assertArrayHasKey('items.0.produk_id', $tanpaProduk['errors']);
        $this->assertArrayHasKey('items.0.kuantitas', $tanpaProduk['errors']);

        $this->assertSame(0, Transaksi::where('jenis_transaksi', 'penjualan')->count());
    }

    public function test_stok_kurang_tetap_tersimpan_sebagai_draft_pesanan(): void
    {
        $res = Livewire::actingAs($this->admin)
            ->test(Create::class, ['jenis' => 'penjualan'])
            ->call('save', $this->saleForm(['items' => [['produk_id' => $this->produk->id, 'kuantitas' => '50', 'harga' => '20000']]]))
            ->effects['returns'][0];

        $this->assertTrue($res['ok']);
        $this->assertFalse($res['posted']);
        $this->assertStringContainsString('DRAFT', $res['warning']);
        $this->assertSame(TransaksiStatus::Draft, Transaksi::findOrFail($res['id'])->status);
    }

    public function test_pembelian_kredit_memperbarui_harga_beli_master_bila_dikonfirmasi(): void
    {
        $pemasok = Pihak::factory()->pemasok()->create();

        $res = Livewire::actingAs($this->admin)
            ->test(Create::class, ['jenis' => 'pembelian'])
            ->call('save', $this->saleForm([
                'metode_pembayaran' => 'kredit', 'akun_kas_id' => null, 'pihak_id' => $pemasok->id,
                'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '4', 'harga' => '12500']],
            ]), true, true)
            ->effects['returns'][0];

        $this->assertTrue($res['ok'] && $res['posted']);
        $trx = Transaksi::findOrFail($res['id']);
        $this->assertSame('2026-01-20', $trx->jatuh_tempo->toDateString()); // bayar borongan berikutnya (tgl 20)
        $this->assertSame('12500.00', $this->produk->fresh()->harga_beli);
        $this->assertSame('14.0000', $this->produk->fresh()->stok_terkini);
    }

    public function test_tambah_produk_baru_dari_autocomplete(): void
    {
        $res = Livewire::actingAs($this->admin)
            ->test(Create::class, ['jenis' => 'penjualan'])
            ->call('createProduct', ['nama_produk' => 'Nasi Kotak Spesial', 'satuan' => 'Ktk', 'kategori' => 'konsumsi', 'harga_beli' => '25000', 'harga_jual' => '30000'])
            ->effects['returns'][0];

        $this->assertTrue($res['ok']);
        $this->assertSame('KSM-001', $res['produk']['kode']);
        $this->assertEquals(30000, $res['produk']['harga_jual']);
        $this->assertDatabaseHas('produk', ['nama_produk' => 'Nasi Kotak Spesial', 'kategori' => 'konsumsi', 'unit_usaha_id' => UnitUsaha::where('kode_unit', 'UNIT-KONSUMSI')->value('id')]);
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
