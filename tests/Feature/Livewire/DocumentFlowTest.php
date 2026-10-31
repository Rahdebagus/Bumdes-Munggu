<?php

namespace Tests\Feature\Livewire;

use App\Domain\Accounting\PostDocument;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Livewire\Accounting\Journal;
use App\Livewire\Accounting\Transactions\Index as TransactionsIndex;
use App\Livewire\Accounting\Transactions\Show;
use App\Livewire\Cash\Create as CashCreate;
use App\Livewire\Cash\Settlements;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tugas C/D/E: form kas (akun lawan otomatis, kategori dialihkan), pelunasan
 * hutang borongan, Daftar Transaksi (hapus draft, Batal Posting), Jurnal
 * Umum, dan laporan turunan + ekspor.
 */
class DocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Produk $produk;

    private Pihak $pemasok;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);
        $this->produk = Produk::factory()->create(['kategori' => 'konsumsi', 'unit_usaha_id' => UnitUsaha::where('kode_unit', 'UNIT-KONSUMSI')->value('id')]);
        $this->pemasok = Pihak::factory()->pemasok()->create();
    }

    private function akun(string $kode): int
    {
        return (int) Akun::where('kode_akun', $kode)->value('id');
    }

    private function purchaseOnCredit(string $qty, string $harga, string $tanggal = '2026-01-05'): Transaksi
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal, 'unit_usaha_id' => $this->produk->unit_usaha_id, 'pihak_id' => $this->pemasok->id,
            'metode_pembayaran' => 'kredit', 'jatuh_tempo' => '2026-01-10',
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => $qty, 'harga' => $harga]],
        ], TransaksiJenis::Pembelian, $this->admin);

        return app(PostDocument::class)->handle($draft, $this->admin);
    }

    public function test_form_kas_memakai_akun_lawan_otomatis_dan_menolak_lawan_di_luar_kategori(): void
    {
        $t = Livewire::actingAs($this->admin)->test(CashCreate::class)->assertSee('Form ini untuk kas masuk/keluar di luar jual-beli barang');

        $ok = $t->call('save', [
            'tanggal' => '2026-01-12', 'kategori_kode' => 'biaya_transport', 'akun_kas_id' => $this->akun('1.1.01.01'),
            'akun_lawan_id' => $this->akun('6.1.2'), 'nominal' => '45000', 'keterangan' => 'Bensin antar konsumsi',
        ])->effects['returns'][0];
        $this->assertTrue($ok['ok'] && $ok['posted']);
        $this->assertMatchesRegularExpression('/^KS-202601-\d{4}$/', $ok['nomor']);

        $salah = $t->call('save', [
            'tanggal' => '2026-01-12', 'kategori_kode' => 'biaya_transport', 'akun_kas_id' => $this->akun('1.1.01.01'),
            'akun_lawan_id' => $this->akun('4.4.1'), 'nominal' => '1000',
        ])->effects['returns'][0];
        $this->assertFalse($salah['ok']);
        $this->assertArrayHasKey('akun_lawan_id', $salah['errors']);

        $alih = $t->call('save', ['tanggal' => '2026-01-12', 'kategori_kode' => 'penjualan_barang', 'akun_kas_id' => $this->akun('1.1.01.01'), 'nominal' => '1000'])->effects['returns'][0];
        $this->assertFalse($alih['ok']);
        $this->assertStringContainsString('menu Penjualan', $alih['message']);
    }

    public function test_pelunasan_hutang_membayar_beberapa_nota_sekaligus(): void
    {
        $a = $this->purchaseOnCredit('10', '15000');
        $b = $this->purchaseOnCredit('20', '15000', '2026-01-08');

        Livewire::actingAs($this->admin)->test(Settlements::class, ['mode' => 'hutang'])
            ->assertSee($a->nomor_transaksi)->assertSee($b->nomor_transaksi)
            ->call('toggleAll', true)
            ->set('tanggal', '2026-01-10')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertDontSee($a->nomor_transaksi);

        $bayar = Transaksi::where('kategori_kode', 'pelunasan_utang')->firstOrFail();
        $this->assertSame(TransaksiStatus::Posted, $bayar->status);
        $this->assertSame('450000.00', $bayar->total);
        $this->assertSame($this->pemasok->id, $bayar->pihak_id);
    }

    public function test_hapus_draft_dan_dokumen_terposting_tidak_bisa_dihapus(): void
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-05', 'unit_usaha_id' => $this->produk->unit_usaha_id, 'pihak_id' => null, 'metode_pembayaran' => 'tunai',
            'akun_kas_id' => $this->akun('1.1.01.01'),
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '1', 'harga' => '1000']],
        ], TransaksiJenis::Pembelian, $this->admin);
        $posted = $this->purchaseOnCredit('1', '1000');

        Livewire::actingAs($this->admin)->test(TransactionsIndex::class)
            ->assertSee('Edit')
            ->call('deleteDraft', $draft->id)
            ->call('deleteDraft', $posted->id)
            ->assertDispatched('toast', type: 'error');

        $this->assertNull(Transaksi::find($draft->id));
        $this->assertNotNull(Transaksi::find($posted->id));
    }

    public function test_batal_posting_membuat_pembalik_dan_draft_koreksi(): void
    {
        $posted = $this->purchaseOnCredit('10', '15000');
        $this->assertSame('10.0000', $this->produk->fresh()->stok_terkini);

        Livewire::actingAs($this->admin)->test(Show::class, ['transaksi' => $posted])
            ->set('reverseReason', 'salah qty')
            ->call('cancelPosting')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(TransaksiStatus::Reversed, $posted->fresh()->status);
        $this->assertSame('0.0000', $this->produk->fresh()->stok_terkini, 'stok kembali');
        $koreksi = Transaksi::where('status', 'draft')->where('jenis_transaksi', 'pembelian')->firstOrFail();
        $this->assertSame('10.0000', $koreksi->barang()->first()->kuantitas);
        $this->assertStringContainsString($posted->nomor_transaksi, $koreksi->keterangan);
    }

    public function test_jurnal_umum_hanya_dokumen_terposting_dan_seimbang(): void
    {
        $posted = $this->purchaseOnCredit('10', '15000');
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-06', 'unit_usaha_id' => $this->produk->unit_usaha_id, 'pihak_id' => $this->pemasok->id,
            'metode_pembayaran' => 'kredit', 'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '1', 'harga' => '1']],
        ], TransaksiJenis::Pembelian, $this->admin);

        Livewire::actingAs($this->admin)->test(Journal::class, ['bulan' => 1, 'tahun' => 2026])
            ->assertSee($posted->nomor_transaksi)
            ->assertDontSee($draft->nomor_transaksi)
            ->assertSee('1.1.05.01 — Persediaan Barang Dagang')
            ->assertSee('2.1.00.01 — Utang Usaha')
            ->assertSee('Seimbang');
    }

    public function test_laporan_turunan_merender_dan_bisa_diekspor(): void
    {
        $this->purchaseOnCredit('10', '15000');

        foreach (['jurnal-umum', 'neraca-saldo', 'buku-kas', 'buku-bank', 'kartu-stok', 'piutang-hutang', 'laba-rugi', 'neraca'] as $type) {
            Livewire::actingAs($this->admin)->test(ReportsIndex::class, ['type' => $type, 'bulan' => 1, 'tahun' => 2026])->assertOk();
        }

        Livewire::actingAs($this->admin)->test(ReportsIndex::class, ['type' => 'piutang-hutang', 'bulan' => 1, 'tahun' => 2026])
            ->assertSee('HUTANG USAHA')->assertSee('150.000');
        Livewire::actingAs($this->admin)->test(ReportsIndex::class, ['type' => 'neraca-saldo', 'bulan' => 1, 'tahun' => 2026])
            ->assertSee('SEIMBANG');

        Storage::fake('local');
        foreach (['pdf', 'xlsx'] as $format) {
            $this->actingAs($this->admin)
                ->get(route('reports.export', ['type' => 'jurnal-umum', 'format' => $format, 'from' => '2026-01-01', 'to' => '2026-01-31']))
                ->assertOk()
                ->assertDownload("jurnal-umum_2026-01_semua-unit.{$format}");
        }
    }

    public function test_halaman_baru_dan_edit_draft_terbuka(): void
    {
        $draftBeli = app(SaveTradingDraft::class)->handle([
            'tanggal' => '2026-01-05', 'unit_usaha_id' => $this->produk->unit_usaha_id, 'pihak_id' => null, 'metode_pembayaran' => 'tunai',
            'akun_kas_id' => $this->akun('1.1.01.01'), 'items' => [['produk_id' => $this->produk->id, 'kuantitas' => '2', 'harga' => '1000']],
        ], TransaksiJenis::Pembelian, $this->admin);

        $this->actingAs($this->admin);
        foreach ([
            route('sales.create'), route('purchases.create'), route('cash.create'), route('cash.create', ['kategori' => 'pelunasan_utang']),
            route('settlements.payable'), route('settlements.receivable'), route('journal.index'),
            route('master.products'), route('transactions.index'), route('purchases.create', ['draft' => $draftBeli->id]),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // Form edit draft memuat isi draft ke Alpine (JSON config).
        $this->get(route('purchases.create', ['draft' => $draftBeli->id]))->assertSee($draftBeli->nomor_transaksi);
        // Draft pembelian tidak boleh dibuka di form penjualan.
        $this->get(route('sales.create', ['draft' => $draftBeli->id]))->assertNotFound();
    }

    public function test_nota_bisa_dicetak(): void
    {
        $posted = $this->purchaseOnCredit('10', '15000');

        $this->actingAs($this->admin)->get(route('transactions.nota', $posted))
            ->assertOk()->assertSee('NOTA PEMBELIAN')->assertSee($posted->nomor_transaksi)->assertSee('Rp 150.000');
    }
}
