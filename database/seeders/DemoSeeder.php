<?php

namespace Database\Seeders;

use App\Domain\Accounting\PostDocument;
use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\SaveManualJournal;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\FixedAssets\PostDepreciation;
use App\Domain\FixedAssets\RegisterAsset;
use App\Domain\Inventory\PostOpeningStock;
use App\Domain\Lending\DisburseLoan;
use App\Domain\Lending\SaveInstallmentPayment;
use App\Domain\Lending\SaveLoanDraft;
use App\Domain\MasterData\ImportProducts;
use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
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
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data LATIHAN kecil & mudah ditelusuri untuk pengembangan/UAT — TERPISAH dari
 * produksi dan MENOLAK berjalan di environment production. Satu periode
 * (Januari 2026); semua transaksi lewat Actions/Services aplikasi sehingga
 * setiap laporan dapat dicocokkan manual dengan ringkasan di bawah.
 *
 * Master: 159 produk BUMDes; 16 produk latihan berstok 20–50 (+ jasa Fotocopy).
 *
 * MODAL DESA MUNGGU = Rp 50.000.000 (seimbang):
 *   Saldo awal : Kas 10.000.000 + Bank 30.032.500        = Modal 40.032.500
 *   Stok awal  : 16 produk @20–50 unit, nilai 9.967.500   → Modal  9.967.500
 *
 * Transaksi Januari 2026:
 *   Pembelian kredit  : Nasi Bungkus 100, Snack Kotak 50 = 1.800.000 (lunas 20/01 dari bank)
 *   Penjualan         : Konsumsi 3.130.000 · ATK+fotocopy 455.000 · Upakara (kredit) 750.000
 *                       · Perdagangan 428.000 = 4.763.000;  HPP 4.132.000 → Laba kotor 631.000
 *   Pendapatan lain   : Desa Wisata 500.000 + Fee Pupuk 150.000 + Bunga Bank 25.000
 *                       + Bunga Pedagang 12.000 = 687.000
 *   Biaya operasional : Pegawai 500.000 + Transport 100.000 + Kantor 50.000 + Penyusutan 100.000 = 750.000
 *   Biaya non operas. : Pajak Bunga Bank 5.000 + Administrasi Bank 10.000 = 15.000
 *   Pajak             : 50.000                 → LABA BERSIH JANUARI 503.000
 *   Lainnya           : pelunasan piutang 500.000 (sisa piutang 250.000), tarik bank ke kas
 *                       2.000.000, dana sosial 100.000, pinjaman pedagang 1.200.000 (angsuran ke-1
 *                       100.000 + bunga 12.000), etalase 1.200.000.
 *
 * Neraca 31/01/2026: Kas 14.125.000 · Bank 26.192.500 · Piutang Usaha 250.000 · Piutang Pedagang
 *   1.100.000 · Persediaan 7.635.500 · Inventaris neto 1.100.000 = 50.403.000
 *   = Modal 50.000.000 − Dana Sosial 100.000 + Laba 503.000.
 *
 * Akun demo (password: demo12345) — hanya non-produksi:
 *   admin.demo@bumdes-munggu.test    (admin)
 *   pimpinan.demo@bumdes-munggu.test (pimpinan, untuk menguji persetujuan)
 */
class DemoSeeder extends Seeder
{
    public const MODAL = '50000000';

    public const KAS_AWAL = '10000000';

    /** Produk latihan: nama (sesuai daftar produk BUMDes) => stok awal (20–50). */
    public const STOK = [
        'Nasi Bungkus' => 50,
        'Nasi Kotak' => 50,
        'Snack Kotak' => 50,
        'Snack bungkus' => 50,
        'Aqua 600 ml' => 20,
        'Kopi Setia Bali' => 20,
        'Gula pasir ( Gulaku)' => 20,
        'Hvs 70 gr F4 sidu' => 20,
        'Bolpoin Joyko GP 330 Ht 0.5mm' => 50,
        'Buku Folio paperline' => 25,
        'Stop Map Folio' => 50,
        'Amplop Paper Line No 90' => 20,
        'Lem Stick Joyko' => 30,
        'OLI ATAS (CASTROL)' => 20,
        'Topi' => 25,
        'Pejati' => 25,
    ];

    private User $actor;

    private PeriodeAkuntansi $periode;

    private UnitUsaha $dagang;

    private UnitUsaha $kredit;

    /** @var array<string, Produk> */
    private array $produk = [];

    /** @var array<string, Pihak> */
    private array $pihak = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di production.');
        }

        $this->call(MasterDataSeeder::class);

        $this->dagang = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->kredit = UnitUsaha::where('kode_unit', 'UNIT-KREDIT')->firstOrFail();
        // Akun demo (hanya non-produksi). Admin jadi actor posting; pimpinan untuk
        // menguji alur persetujuan (Stok Opname, Laporan Omzet Desa).
        $this->actor = User::firstOrCreate(
            ['email' => 'admin.demo@bumdes-munggu.test'],
            ['nama' => 'Ayu Senja', 'role' => 'admin', 'status' => 'aktif', 'password' => bcrypt('demo12345')],
        );
        User::firstOrCreate(
            ['email' => 'pimpinan.demo@bumdes-munggu.test'],
            ['nama' => 'I Wayan Sukada', 'role' => 'pimpinan', 'status' => 'aktif', 'password' => bcrypt('demo12345')],
        );

        $this->seedMaster();
        $this->seedPeriode();
        $this->lockOpening();

        // Idempoten: jangan posting ulang bila demo transaksi sudah ada.
        if (Transaksi::whereNotNull('posted_at')->exists()) {
            return;
        }

        $this->seedOpeningStock();
        $this->seedTrading();
        $this->seedCash();
        $this->seedLoan();
        $this->seedAsset();
        $this->seedTax();
    }

    private function seedMaster(): void
    {
        $pihak = [
            ['kode_pihak' => 'PLG-001', 'jenis_pihak' => 'pelanggan', 'nama' => 'Warung Bu Rai', 'status' => 'aktif'],
            ['kode_pihak' => 'PMS-001', 'jenis_pihak' => 'pemasok', 'nama' => 'CV Sumber Tani', 'status' => 'aktif'],
            ['kode_pihak' => 'PMJ-001', 'jenis_pihak' => 'peminjam', 'nama' => 'I Ketut Alit Suardika', 'status' => 'aktif'],
        ];
        foreach ($pihak as $p) {
            $this->pihak[$p['kode_pihak']] = Pihak::updateOrCreate(['kode_pihak' => $p['kode_pihak']], $p);
        }

        // Master produk BUMDes (tanpa stok); stok latihan diposting terpisah.
        $import = app(ImportProducts::class);
        $import->import($import->read(ProdukBumdesSeeder::FILE));

        foreach (array_merge(array_keys(self::STOK), ['Fotocopy']) as $nama) {
            $this->produk[$nama] = Produk::where('nama_produk', $nama)->firstOrFail();
        }
    }

    private function seedPeriode(): void
    {
        $this->periode = PeriodeAkuntansi::firstOrCreate(
            ['tahun' => 2026, 'bulan' => 1],
            ['tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-01-31', 'status' => 'terbuka', 'revision' => 0],
        );
    }

    /** Nilai stok awal latihan = Σ stok × harga beli. */
    public static function nilaiStokAwal(): string
    {
        $total = '0';
        foreach (self::STOK as $nama => $qty) {
            $total = Decimal::add($total, Decimal::mul($qty, (string) Produk::where('nama_produk', $nama)->value('harga_beli')));
        }

        return $total;
    }

    /**
     * Saldo awal cutover (Januari) seimbang + terkunci. Kas + Bank = Modal;
     * bank dihitung agar Modal setelah stok awal tepat Rp 50.000.000.
     */
    private function lockOpening(): void
    {
        if ($this->periode->opening_locked_at !== null) {
            return;
        }

        $kasBank = Decimal::sub(self::MODAL, self::nilaiStokAwal());
        $bank = Decimal::sub($kasBank, self::KAS_AWAL);

        $rows = [
            ['kode_akun' => '1.1.01.01', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => self::KAS_AWAL, 'saldo_kredit' => '0', 'keterangan' => 'Kas awal'],
            ['kode_akun' => '1.1.01.02', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => $bank, 'saldo_kredit' => '0', 'keterangan' => 'Bank awal'],
            ['kode_akun' => '3.1.01.00', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '0', 'saldo_kredit' => $kasBank, 'keterangan' => 'Modal Desa Munggu (bagian kas & bank)'],
        ];

        (new CommitImport)->handle($this->periode, (new PreviewImport)->handle($rows), $this->actor);
        app(LockOpeningBalance::class)->handle($this->periode, $this->actor);
    }

    /** Stok awal 1 Januari: D Persediaan — K Modal Desa (per unit usaha). */
    private function seedOpeningStock(): void
    {
        $lines = [];
        foreach (self::STOK as $nama => $qty) {
            $lines[] = ['produk' => $this->produk[$nama], 'kuantitas' => (string) $qty, 'harga' => (string) $this->produk[$nama]->harga_beli];
        }

        app(PostOpeningStock::class)->handle($lines, '2026-01-01', $this->actor);
    }

    private function seedTrading(): void
    {
        // Pembelian kredit konsumsi (dibayar borongan tgl 20).
        $beli = $this->trade(TransaksiJenis::Pembelian, '2026-01-03', 'kredit', 'PMS-001', [
            ['Nasi Bungkus', '100', '15000'], ['Snack Kotak', '50', '6000'],
        ]);

        // Penjualan tunai konsumsi.
        $this->trade(TransaksiJenis::Penjualan, '2026-01-10', 'tunai', null, [
            ['Nasi Bungkus', '120', '17500'], ['Snack Kotak', '80', '7000'], ['Aqua 600 ml', '10', '47000'],
        ]);

        // Penjualan tunai ATK + jasa fotocopy.
        $this->trade(TransaksiJenis::Penjualan, '2026-01-12', 'tunai', null, [
            ['Hvs 70 gr F4 sidu', '5', '55000'], ['Bolpoin Joyko GP 330 Ht 0.5mm', '20', '1500'], ['Fotocopy', '500', '300'],
        ]);

        // Penjualan kredit upakara (menimbulkan piutang usaha).
        $jual = $this->trade(TransaksiJenis::Penjualan, '2026-01-15', 'kredit', 'PLG-001', [['Pejati', '10', '75000']]);

        // Penjualan tunai perdagangan.
        $this->trade(TransaksiJenis::Penjualan, '2026-01-16', 'tunai', null, [['Topi', '5', '50000'], ['OLI ATAS (CASTROL)', '4', '44500']]);

        // Pelunasan hutang dari bank & pelunasan piutang sebagian ke kas.
        $this->cash('pelunasan_utang', '2026-01-20', null, '1.1.01.02', [['invoice_transaksi_id' => $beli->id, 'nominal' => '1800000']]);
        $this->cash('pelunasan_piutang', '2026-01-25', null, '1.1.01.01', [['invoice_transaksi_id' => $jual->id, 'nominal' => '500000']]);
    }

    private function seedCash(): void
    {
        foreach ([
            ['pendapatan_wisata', '500000', '1.1.01.01', 'Tiket Desa Wisata'],
            ['fee_pupuk', '150000', '1.1.01.01', 'Fee penyaluran pupuk'],
            ['bunga_bank', '25000', '1.1.01.02', 'Bunga BPD Januari'],
            ['pajak_bunga_bank', '5000', '1.1.01.02', 'Pajak bunga BPD'],
            ['admin_bank', '10000', '1.1.01.02', 'Administrasi BPD'],
            ['tarik_bank', '2000000', '1.1.01.01', 'Penambahan kas dari bank'],
            ['biaya_pegawai', '500000', '1.1.01.01', 'Gaji pegawai Januari'],
            ['biaya_transport', '100000', '1.1.01.01', 'Transport antar pesanan'],
            ['biaya_kantor', '50000', '1.1.01.01', 'Alat tulis kantor'],
            ['dana_sosial', '100000', '1.1.01.01', 'Santunan warga'],
        ] as [$kategori, $nominal, $kas, $ket]) {
            $this->cash($kategori, '2026-01-27', $nominal, $kas, [], $ket);
        }
    }

    private function seedLoan(): void
    {
        $loan = app(SaveLoanDraft::class)->handle([
            'tanggal_pinjaman' => '2026-01-05',
            'unit_usaha_id' => $this->kredit->id,
            'peminjam_id' => $this->pihak['PMJ-001']->id,
            'pokok_pinjaman' => '1200000',
            'persentase_bunga' => '12',
            'tenor' => 12,
            'basis_bunga' => 'flat_total',
            'tanggal_angsuran_pertama' => '2026-01-25',
        ], $this->actor);

        app(DisburseLoan::class)->handle($loan, '2026-01-05', $this->akunByKode('1.1.01.01'), $this->actor, 'demo-disb', 'demo');

        // Bayar angsuran ke-1 (pokok 100.000 + bunga 12.000).
        $a = $loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();
        $pay = app(SaveInstallmentPayment::class)->handle([
            'tanggal' => '2026-01-25',
            'akun_kas_id' => $this->akunByKode('1.1.01.01'),
            'allocations' => [[
                'angsuran_id' => $a->id,
                'pokok' => (string) $a->pokok_tagihan,
                'bunga' => (string) $a->bunga_tagihan,
                'denda' => '0',
            ]],
        ], $this->actor);
        app(PostingService::class)->post($pay, 1, $this->actor, 'demo-pay', 'demo');
    }

    private function seedAsset(): void
    {
        $this->cash('perolehan_aset', '2026-01-05', '1200000', '1.1.01.01', [], 'Perolehan etalase', $this->akunByKode('1.3.03.01'));

        $aset = app(RegisterAsset::class)->handle([
            'kode_aset' => 'AST-001',
            'unit_usaha_id' => $this->dagang->id,
            'nama_aset' => 'Etalase Toko',
            'tanggal_perolehan' => '2026-01-05',
            'harga_perolehan' => '1200000',
            'nilai_residu' => '0',
            'umur_manfaat' => 12,
            'tanggal_mulai_susut' => '2026-01-31',
        ], $this->actor);

        app(PostDepreciation::class)->handle($aset, $this->periode, $this->actor, 'demo-dep', 'demo');
    }

    /** Pajak usaha Januari (jurnal penyesuaian: D Pajak — K Bank). */
    private function seedTax(): void
    {
        $lines = [
            ['akun_id' => $this->akunByKode('8.0'), 'debit' => '50000', 'kredit' => '0'],
            ['akun_id' => $this->akunByKode('1.1.01.02'), 'debit' => '0', 'kredit' => '50000'],
        ];

        $trx = app(SaveManualJournal::class)->handle([
            'tanggal' => '2026-01-31',
            'periode_id' => $this->periode->id,
            'keterangan' => 'Pajak usaha Januari',
            'lines' => $lines,
        ], $this->actor);

        TransaksiDetail::where('transaksi_id', $trx->id)->where('akun_id', $this->akunByKode('1.1.01.02'))
            ->update(['kelompok_arus_kas' => 'operasi']);

        app(PostingService::class)->post($trx->fresh(['details']), $trx->version, $this->actor, 'demo-pajak', 'demo');
    }

    private function akunByKode(string $kode): int
    {
        return (int) Akun::where('kode_akun', $kode)->value('id');
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $items  [nama produk, qty, harga]
     */
    private function trade(TransaksiJenis $jenis, string $tanggal, string $metode, ?string $pihak, array $items): Transaksi
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal,
            'unit_usaha_id' => $this->produk[$items[0][0]]->unit_usaha_id,
            'pihak_id' => $pihak ? $this->pihak[$pihak]->id : null,
            'metode_pembayaran' => $metode,
            'akun_kas_id' => $metode === 'tunai' ? $this->akunByKode('1.1.01.01') : null,
            'jatuh_tempo' => $metode === 'kredit' ? '2026-02-20' : null,
            'items' => array_map(fn ($i) => [
                'produk_id' => $this->produk[$i[0]]->id,
                'kuantitas' => $i[1],
                'harga' => $i[2],
                'diskon' => '0',
                'pajak' => '0',
            ], $items),
        ], $jenis, $this->actor);

        return app(PostDocument::class)->handle($draft, $this->actor);
    }

    /**
     * Transaksi kas lewat kategori form Pemasukan & Pengeluaran (akun lawan
     * default kategori bila tidak diberikan).
     *
     * @param  array<int, array{invoice_transaksi_id: int, nominal: string}>  $allocations
     */
    private function cash(string $kategori, string $tanggal, ?string $nominal, string $kasKode, array $allocations = [], ?string $keterangan = null, ?int $lawanId = null): Transaksi
    {
        $default = config("accounting.categories.{$kategori}.default_lawan");

        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => $tanggal,
            'kategori_kode' => $kategori,
            'akun_kas_id' => $this->akunByKode($kasKode),
            'akun_lawan_id' => $lawanId ?? ($default ? $this->akunByKode($default) : null),
            'nominal' => $nominal ?? '0',
            'allocations' => $allocations,
            'keterangan' => $keterangan,
            'pihak_id' => $allocations ? Transaksi::find($allocations[0]['invoice_transaksi_id'])?->pihak_id : null,
        ], $this->actor);

        return app(PostDocument::class)->handle($draft, $this->actor);
    }
}
