<?php

namespace Tests\Feature\Accounting;

use App\Domain\Accounting\PostDocument;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\Inventory\PostOpeningStock;
use App\Domain\Lending\DisburseLoan;
use App\Domain\Lending\SaveInstallmentPayment;
use App\Domain\Lending\SaveLoanDraft;
use App\Domain\Reporting\ReportService;
use App\Domain\Shared\Decimal;
use App\Domain\Trading\SaveTradingDraft;
use App\Enums\TransaksiJenis;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Produk;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan jurnal otomatis BUMDes Munggu (revisi-flow Tugas F): satu transaksi
 * contoh per aturan; jurnal yang terbentuk harus seimbang dan memakai akun
 * bernomor BUMDes yang benar. Persediaan perpetual: pembelian barang = D
 * Persediaan, penjualan = D HPP kategori / K Persediaan (keputusan pengguna).
 */
class JournalRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Produk $nasi;   // konsumsi

    private Produk $hvs;    // atk

    private Produk $fotocopy; // jasa (tanpa stok)

    private Pihak $pelanggan;

    private Pihak $pemasok;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->admin = User::factory()->create();
        PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka', 'opening_locked_at' => now()]);

        $unit = fn (string $kode) => UnitUsaha::where('kode_unit', $kode)->value('id');
        $this->nasi = Produk::factory()->create(['nama_produk' => 'Nasi Bungkus', 'kategori' => 'konsumsi', 'unit_usaha_id' => $unit('UNIT-KONSUMSI'), 'harga_beli' => 15000, 'harga_jual' => 17500]);
        $this->hvs = Produk::factory()->create(['nama_produk' => 'HVS F4', 'kategori' => 'atk', 'unit_usaha_id' => $unit('UNIT-ATK'), 'harga_beli' => 51000, 'harga_jual' => 55000]);
        $this->fotocopy = Produk::factory()->create(['nama_produk' => 'Fotocopy', 'kategori' => 'jasa', 'lacak_stok' => false, 'unit_usaha_id' => $unit('UNIT-ATK'), 'harga_beli' => 250, 'harga_jual' => 300]);
        $this->pelanggan = Pihak::factory()->create();
        $this->pemasok = Pihak::factory()->pemasok()->create();
    }

    // ------------------------------------------------------------------ helpers

    private function akun(string $kode): int
    {
        return (int) Akun::where('kode_akun', $kode)->valueOrFail('id');
    }

    /**
     * Jurnal transaksi harus persis berisi baris [kode, debit, kredit] (urutan
     * bebas, baris dengan akun sama dijumlah) dan seimbang.
     *
     * @param  array<int, array{0: string, 1: string|int, 2: string|int}>  $expected
     */
    private function assertJournal(Transaksi $trx, array $expected): void
    {
        $actual = [];
        foreach (TransaksiDetail::with('akun')->where('transaksi_id', $trx->id)->get() as $d) {
            $k = $d->akun->kode_akun;
            $actual[$k] = [Decimal::add($actual[$k][0] ?? '0', (string) $d->debit), Decimal::add($actual[$k][1] ?? '0', (string) $d->kredit)];
        }
        $want = [];
        foreach ($expected as [$kode, $debit, $kredit]) {
            $want[$kode] = [Decimal::add($want[$kode][0] ?? '0', $debit), Decimal::add($want[$kode][1] ?? '0', $kredit)];
        }
        ksort($actual);
        ksort($want);
        $this->assertSame($want, $actual, "Jurnal {$trx->nomor_transaksi} tidak sesuai aturan.");

        $d = array_reduce($actual, fn ($s, $r) => Decimal::add($s, $r[0]), '0');
        $k = array_reduce($actual, fn ($s, $r) => Decimal::add($s, $r[1]), '0');
        $this->assertSame($d, $k, 'Jurnal tidak seimbang (debet ≠ kredit).');
    }

    private function posting(Transaksi $draft): Transaksi
    {
        return app(PostDocument::class)->handle($draft, $this->admin);
    }

    /** @param array<int, array{0: Produk, 1: string, 2: string}> $lines */
    private function trade(TransaksiJenis $jenis, string $metode, array $lines, ?int $akunKas = null, ?Pihak $pihak = null, string $tanggal = '2026-01-10'): Transaksi
    {
        return $this->posting(app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal,
            'unit_usaha_id' => $lines[0][0]->unit_usaha_id,
            'pihak_id' => $pihak?->id,
            'metode_pembayaran' => $metode,
            'akun_kas_id' => $metode === 'tunai' ? ($akunKas ?? $this->akun('1.1.01.01')) : null,
            'jatuh_tempo' => $metode === 'kredit' ? '2026-01-31' : null,
            'items' => array_map(fn ($l) => ['produk_id' => $l[0]->id, 'kuantitas' => $l[1], 'harga' => $l[2]], $lines),
        ], $jenis, $this->admin));
    }

    private function cash(string $kategori, string $nominal, string $kasKode = '1.1.01.01', ?string $lawanKode = null): Transaksi
    {
        return $this->posting(app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-12',
            'kategori_kode' => $kategori,
            'akun_kas_id' => $this->akun($kasKode),
            'akun_lawan_id' => $this->akun($lawanKode ?? config("accounting.categories.{$kategori}.default_lawan")),
            'nominal' => $nominal,
        ], $this->admin));
    }

    private function stockUp(): void
    {
        $this->trade(TransaksiJenis::Pembelian, 'tunai', [[$this->nasi, '100', '15000'], [$this->hvs, '10', '51000']], tanggal: '2026-01-05');
    }

    // -------------------------------------------------------------------- rules

    public function test_penjualan_tunai_kredit_penjualan_dan_hpp_per_kategori(): void
    {
        $this->stockUp();
        $sale = $this->trade(TransaksiJenis::Penjualan, 'tunai', [[$this->nasi, '10', '17500'], [$this->hvs, '2', '55000'], [$this->fotocopy, '100', '300']]);

        $this->assertJournal($sale, [
            ['1.1.01.01', '315000', '0'],          // D Kas: 175.000 + 110.000 + 30.000
            ['4.1.1', '0', '175000'],             // K Penjualan Jasa Konsumsi
            ['4.1.3', '0', '140000'],             // K Penjualan ATK (ATK + jasa fotocopy)
            ['5.1.1', '150000', '0'],             // D HPP Konsumsi 10 × 15.000
            ['5.1.3', '102000', '0'],             // D HPP ATK 2 × 51.000 (fotocopy tanpa HPP stok)
            ['1.1.05.01', '0', '252000'],         // K Persediaan
        ]);
        $this->assertSame('90.0000', $this->nasi->fresh()->stok_terkini);
        $this->assertSame('0.0000', $this->fotocopy->fresh()->stok_terkini, 'jasa tidak bermutasi stok');
    }

    public function test_penjualan_ke_bank_mendebet_kas_di_bank(): void
    {
        $this->stockUp();
        $sale = $this->trade(TransaksiJenis::Penjualan, 'tunai', [[$this->nasi, '2', '17500']], $this->akun('1.1.01.02'));

        $this->assertJournal($sale, [['1.1.01.02', '35000', '0'], ['4.1.1', '0', '35000'], ['5.1.1', '30000', '0'], ['1.1.05.01', '0', '30000']]);
    }

    public function test_penjualan_kredit_mendebet_piutang_usaha(): void
    {
        $this->stockUp();
        $sale = $this->trade(TransaksiJenis::Penjualan, 'kredit', [[$this->nasi, '20', '17500']], pihak: $this->pelanggan);

        $this->assertJournal($sale, [['1.1.03.01', '350000', '0'], ['4.1.1', '0', '350000'], ['5.1.1', '300000', '0'], ['1.1.05.01', '0', '300000']]);
    }

    public function test_pelunasan_piutang_kas_vs_piutang_usaha(): void
    {
        $this->stockUp();
        $sale = $this->trade(TransaksiJenis::Penjualan, 'kredit', [[$this->nasi, '20', '17500']], pihak: $this->pelanggan);

        $lunas = $this->posting(app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-20', 'kategori_kode' => 'pelunasan_piutang', 'akun_kas_id' => $this->akun('1.1.01.01'),
            'allocations' => [['invoice_transaksi_id' => $sale->id, 'nominal' => '350000']],
        ], $this->admin));

        $this->assertJournal($lunas, [['1.1.01.01', '350000', '0'], ['1.1.03.01', '0', '350000']]);
    }

    public function test_pembelian_tunai_persediaan_vs_kas_dan_jasa_langsung_hpp(): void
    {
        $buy = $this->trade(TransaksiJenis::Pembelian, 'tunai', [[$this->hvs, '5', '51000'], [$this->fotocopy, '200', '250']]);

        $this->assertJournal($buy, [
            ['1.1.05.01', '255000', '0'],  // D Persediaan (barang ATK)
            ['5.1.3', '50000', '0'],       // D HPP ATK (jasa tanpa stok)
            ['1.1.01.01', '0', '305000'],  // K Kas
        ]);
        $this->assertSame('5.0000', $this->hvs->fresh()->stok_terkini);
    }

    public function test_pembelian_kredit_persediaan_vs_utang_usaha_lalu_pembayaran_hutang(): void
    {
        $buy = $this->trade(TransaksiJenis::Pembelian, 'kredit', [[$this->nasi, '50', '15000']], pihak: $this->pemasok);
        $this->assertJournal($buy, [['1.1.05.01', '750000', '0'], ['2.1.00.01', '0', '750000']]);

        $bayar = $this->posting(app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-20', 'kategori_kode' => 'pelunasan_utang', 'akun_kas_id' => $this->akun('1.1.01.01'),
            'allocations' => [['invoice_transaksi_id' => $buy->id, 'nominal' => '750000']],
        ], $this->admin));
        $this->assertJournal($bayar, [['2.1.00.01', '750000', '0'], ['1.1.01.01', '0', '750000']]);
    }

    public function test_pencairan_dan_angsuran_pedagang_pantai(): void
    {
        $peminjam = Pihak::factory()->peminjam()->create();
        $loan = app(SaveLoanDraft::class)->handle([
            'tanggal_pinjaman' => '2026-01-10', 'unit_usaha_id' => UnitUsaha::where('kode_unit', 'UNIT-KREDIT')->value('id'),
            'peminjam_id' => $peminjam->id, 'pokok_pinjaman' => '1000000', 'persentase_bunga' => '10', 'tenor' => 2,
            'basis_bunga' => 'flat_total', 'tanggal_angsuran_pertama' => '2026-02-01',
        ], $this->admin);

        $cair = app(DisburseLoan::class)->handle($loan, '2026-01-10', $this->akun('1.1.01.01'), $this->admin, 'disb-'.$loan->id, 'h');
        $this->assertJournal($cair, [['1.1.03.03', '1000000', '0'], ['1.1.01.01', '0', '1000000']]);

        $angsuran = $loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();
        $bayar = $this->posting(app(SaveInstallmentPayment::class)->handle([
            'tanggal' => '2026-01-25', 'akun_kas_id' => $this->akun('1.1.01.01'),
            'allocations' => [['angsuran_id' => $angsuran->id, 'pokok' => '500000', 'bunga' => '50000', 'denda' => '0']],
        ], $this->admin));
        $this->assertJournal($bayar, [['1.1.01.01', '550000', '0'], ['1.1.03.03', '0', '500000'], ['4.4.4', '0', '50000']]);
    }

    public function test_bunga_bank_pajak_dan_administrasi_bank(): void
    {
        $this->assertJournal($this->cash('bunga_bank', '120000', '1.1.01.02'), [['1.1.01.02', '120000', '0'], ['4.4.1', '0', '120000']]);
        $this->assertJournal($this->cash('pajak_bunga_bank', '24000', '1.1.01.02'), [['6.2.1', '24000', '0'], ['1.1.01.02', '0', '24000']]);
        $this->assertJournal($this->cash('admin_bank', '10000', '1.1.01.02'), [['6.2.2', '10000', '0'], ['1.1.01.02', '0', '10000']]);
    }

    public function test_penambahan_kas_dari_bank_bukan_arus_kas(): void
    {
        $trx = $this->cash('tarik_bank', '2000000');
        $this->assertJournal($trx, [['1.1.01.01', '2000000', '0'], ['1.1.01.02', '0', '2000000']]);

        // Pemindahan antar kas–bank tidak menambah/mengurangi arus kas.
        $arus = app(ReportService::class)->arusKas('2026-01-01', '2026-01-31');
        $this->assertSame('0.00', $arus['arus_bersih']);

        $setor = $this->cash('setor_bank', '500000');
        $this->assertJournal($setor, [['1.1.01.02', '500000', '0'], ['1.1.01.01', '0', '500000']]);
    }

    public function test_biaya_operasional_mendebet_akun_biaya_otomatis(): void
    {
        foreach ([
            'biaya_pegawai' => '6.1.1', 'biaya_transport' => '6.1.2', 'biaya_kantor' => '6.1.3',
            'bpjs' => '6.1.5', 'biaya_lain' => '6.1.9',
        ] as $kategori => $kode) {
            $this->assertJournal($this->cash($kategori, '100000'), [[$kode, '100000', '0'], ['1.1.01.01', '0', '100000']]);
        }
    }

    public function test_pendapatan_lain_desa_wisata_dan_fee_pupuk(): void
    {
        $this->assertJournal($this->cash('pendapatan_wisata', '300000'), [['1.1.01.01', '300000', '0'], ['4.4.2', '0', '300000']]);
        $this->assertJournal($this->cash('fee_pupuk', '75000'), [['1.1.01.01', '75000', '0'], ['4.4.3', '0', '75000']]);
    }

    public function test_dana_sosial_mendebet_dana_sosial(): void
    {
        $this->assertJournal($this->cash('dana_sosial', '250000'), [['3.2.10.00', '250000', '0'], ['1.1.01.01', '0', '250000']]);
    }

    public function test_stok_awal_mendebet_persediaan_mengkredit_modal(): void
    {
        $hasil = app(PostOpeningStock::class)->handle([
            ['produk' => $this->hvs, 'kuantitas' => '8', 'harga' => '51000'],
            ['produk' => $this->fotocopy, 'kuantitas' => '5', 'harga' => '250'], // jasa → dilewati
        ], '2026-01-01', $this->admin);

        $this->assertCount(1, $hasil['transaksi']);
        $this->assertJournal($hasil['transaksi'][0], [['1.1.05.01', '408000', '0'], ['3.1.01.00', '0', '408000']]);
        $this->assertSame('8.0000', $this->hvs->fresh()->stok_terkini);

        // Rekonsiliasi stok: subledger = akun persediaan.
        $rekon = collect(app(ReportService::class)->reconciliation('2026-01-01', '2026-01-31'))->keyBy('key');
        $this->assertTrue($rekon['stok']['match']);
        $this->assertTrue($rekon['jurnal']['match']);
    }

    public function test_kategori_redirect_ditolak_di_form_kas(): void
    {
        $this->expectExceptionMessage('Pembayaran Angsuran');
        app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-12', 'kategori_kode' => 'setoran_pedagang_pokok',
            'akun_kas_id' => $this->akun('1.1.01.01'), 'nominal' => '1000',
        ], $this->admin);
    }
}
