<?php

namespace Database\Seeders;

use App\Domain\Accounting\PostingService;
use App\Domain\Accounting\SaveManualJournal;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\FixedAssets\PostDepreciation;
use App\Domain\FixedAssets\RegisterAsset;
use App\Domain\Lending\DisburseLoan;
use App\Domain\Lending\SaveInstallmentPayment;
use App\Domain\Lending\SaveLoanDraft;
use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
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
 * Data demo KECIL & mudah ditelusuri untuk pengembangan/UAT — TERPISAH dari
 * produksi dan MENOLAK berjalan di environment production (dokumen13 tahap02).
 * Satu periode (Januari 2026) dengan angka bulat sehingga setiap baris laporan
 * dapat dihitung manual. Semua transaksi diposting lewat Actions/Services
 * aplikasi (bukan menulis ledger langsung) agar alurnya benar.
 *
 * Ringkasan angka (lihat docs/PANDUAN-INPUT-DATA.md):
 *   Saldo awal : Kas 5.000.000 + Bank 25.000.000 = Modal/Dana 30.000.000
 *   Penjualan  : 6.800.000   HPP 5.400.000   → Laba kotor 1.400.000
 *   Pend. lain : bunga kredit 12.000 + bunga bank 25.000 = 37.000
 *   Beban      : gaji 500.000 + transport 100.000 + kantor 50.000 + susut 100.000 = 750.000
 *   Pajak usaha: 50.000  → Laba bersih Januari 637.000
 */
class DemoSeeder extends Seeder
{
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
        $this->actor = User::where('role', 'admin')->first() ?? User::create([
            'nama' => 'Ayu Senja',
            'email' => 'admin.demo@bumdes-munggu.test',
            'role' => 'admin',
            'status' => 'aktif',
            'password' => bcrypt('demo12345'),
        ]);

        $this->seedMaster();
        $this->seedPeriode();
        $this->lockOpening();

        // Idempoten: jangan posting ulang bila demo transaksi sudah ada.
        if (Transaksi::whereNotNull('posted_at')->exists()) {
            return;
        }

        $this->seedTrading();
        $this->seedExpenses();
        $this->seedLoan();
        $this->seedAsset();
    }

    private function seedMaster(): void
    {
        $pihak = [
            ['kode_pihak' => 'CUST-001', 'jenis_pihak' => 'pelanggan', 'nama' => 'Warung Bu Rai', 'status' => 'aktif'],
            ['kode_pihak' => 'SUPP-001', 'jenis_pihak' => 'pemasok', 'nama' => 'CV Sumber Tani', 'status' => 'aktif'],
            ['kode_pihak' => 'PMJ-001', 'jenis_pihak' => 'peminjam', 'nama' => 'I Ketut Alit Suardika', 'status' => 'aktif'],
        ];
        foreach ($pihak as $p) {
            $this->pihak[$p['kode_pihak']] = Pihak::updateOrCreate(['kode_pihak' => $p['kode_pihak']], $p);
        }

        $produk = [
            ['kode_produk' => 'BRG-001', 'nama_produk' => 'Beras 5kg', 'satuan' => 'sak', 'harga_beli' => 60_000, 'harga_jual' => 75_000, 'stok_minimum' => 10],
            ['kode_produk' => 'BRG-002', 'nama_produk' => 'Minyak Goreng 1L', 'satuan' => 'pcs', 'harga_beli' => 15_000, 'harga_jual' => 20_000, 'stok_minimum' => 15],
        ];
        foreach ($produk as $b) {
            $this->produk[$b['kode_produk']] = Produk::updateOrCreate(
                ['kode_produk' => $b['kode_produk']],
                array_merge($b, [
                    'unit_usaha_id' => $this->dagang->id,
                    'stok_terkini' => 0,
                    'harga_pokok_rata' => 0,
                    'status' => 'aktif',
                ]),
            );
        }
    }

    private function seedPeriode(): void
    {
        $this->periode = PeriodeAkuntansi::firstOrCreate(
            ['tahun' => 2026, 'bulan' => 1],
            ['tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-01-31', 'status' => 'terbuka', 'revision' => 0],
        );
    }

    /** Saldo awal cutover (Januari) seimbang + terkunci: Kas/Bank vs Modal & Dana. */
    private function lockOpening(): void
    {
        if ($this->periode->opening_locked_at !== null) {
            return;
        }

        $rows = [
            ['kode_akun' => '1-1010', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '5000000', 'saldo_kredit' => '0', 'keterangan' => 'Kas awal'],
            ['kode_akun' => '1-1020', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '25000000', 'saldo_kredit' => '0', 'keterangan' => 'Bank awal'],
            ['kode_akun' => '3-3010', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '0', 'saldo_kredit' => '25000000', 'keterangan' => 'Modal dana desa'],
            ['kode_akun' => '3-3040', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '0', 'saldo_kredit' => '3000000', 'keterangan' => 'Dana cadangan'],
            ['kode_akun' => '3-3050', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '0', 'saldo_kredit' => '2000000', 'keterangan' => 'Dana pendidikan'],
        ];

        (new CommitImport)->handle($this->periode, (new PreviewImport)->handle($rows), $this->actor);
        app(LockOpeningBalance::class)->handle($this->periode, $this->actor);
    }

    private function cash(): int
    {
        return $this->akunByKode('1-1010');
    }

    private function akunByKode(string $kode): int
    {
        return (int) Akun::where('kode_akun', $kode)->value('id');
    }

    private function akunId(string $key): int
    {
        return (int) Akun::where('system_key', $key)->value('id');
    }

    private function seedTrading(): void
    {
        // Pembelian tunai (menambah stok).
        $this->buy('2026-01-03', [['BRG-001', '100', '60000'], ['BRG-002', '50', '15000']], 'demo-buy-1');

        // Penjualan tunai.
        $this->sell('2026-01-10', 'tunai', 'CUST-001', [['BRG-001', '70', '75000'], ['BRG-002', '40', '20000']], 'demo-sell-1');

        // Penjualan kredit (menimbulkan piutang usaha).
        $this->sell('2026-01-20', 'kredit', 'CUST-001', [['BRG-001', '10', '75000']], 'demo-sell-2');
    }

    private function seedExpenses(): void
    {
        // Beban operasional rinci + pajak usaha (kredit Bank).
        $this->journal('2026-01-27', [
            ['kode' => '6-6040', 'debit' => '500000'],  // Beban Gaji Pegawai
            ['kode' => '6-6050', 'debit' => '100000'],  // Beban Transportasi
            ['kode' => '6-6060', 'debit' => '50000'],   // Beban Biaya Kantor
            ['kode' => '6-6090', 'debit' => '50000'],   // Pajak Usaha
            ['kode' => '1-1020', 'kredit' => '700000', 'arus' => 'operasi'],
        ], 'demo-beban');

        // Pendapatan bunga bank (debit Bank, kredit pendapatan bunga bank).
        $this->journal('2026-01-27', [
            ['kode' => '1-1020', 'debit' => '25000', 'arus' => 'operasi'],
            ['kode' => '4-4050', 'kredit' => '25000'],
        ], 'demo-bunga-bank');
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

        app(DisburseLoan::class)->handle($loan, '2026-01-05', $this->cash(), $this->actor, 'demo-disb', 'demo');

        // Bayar angsuran ke-1 (pokok 100.000 + bunga 12.000).
        $a = $loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();
        $pay = app(SaveInstallmentPayment::class)->handle([
            'tanggal' => '2026-01-25',
            'akun_kas_id' => $this->cash(),
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
        $draft = app(SaveCashDraft::class)->handle([
            'tanggal' => '2026-01-05',
            'unit_usaha_id' => $this->dagang->id,
            'kategori_kode' => 'perolehan_aset',
            'akun_kas_id' => $this->cash(),
            'akun_lawan_id' => $this->akunId('fixed_asset'),
            'nominal' => '1200000',
            'keterangan' => 'Perolehan etalase',
        ], $this->actor);
        app(PostingService::class)->post($draft, 1, $this->actor, 'demo-aset-cash', 'demo');

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

    /**
     * @param  array<int, array{0:string,1:string,2:string}>  $items
     */
    private function buy(string $tanggal, array $items, string $key): void
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal,
            'unit_usaha_id' => $this->dagang->id,
            'pihak_id' => $this->pihak['SUPP-001']->id,
            'metode_pembayaran' => 'tunai',
            'items' => $this->items($items),
        ], TransaksiJenis::Pembelian, $this->actor);

        app(PostingService::class)->post($draft, 1, $this->actor, $key, 'demo');
    }

    /**
     * @param  array<int, array{0:string,1:string,2:string}>  $items
     */
    private function sell(string $tanggal, string $metode, string $customer, array $items, string $key): void
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal,
            'unit_usaha_id' => $this->dagang->id,
            'pihak_id' => $this->pihak[$customer]->id,
            'metode_pembayaran' => $metode,
            'jatuh_tempo' => $metode === 'kredit' ? '2026-02-20' : null,
            'items' => $this->items($items),
        ], TransaksiJenis::Penjualan, $this->actor);

        app(PostingService::class)->post($draft, 1, $this->actor, $key, 'demo');
    }

    /**
     * @param  array<int, array{0:string,1:string,2:string}>  $items
     * @return array<int, array<string, string|int>>
     */
    private function items(array $items): array
    {
        return array_map(fn ($i) => [
            'produk_id' => $this->produk[$i[0]]->id,
            'kuantitas' => $i[1],
            'harga' => $i[2],
            'diskon' => '0',
            'pajak' => '0',
        ], $items);
    }

    /**
     * Jurnal penyesuaian seimbang + posting. $lines: [['kode'=>, 'debit'?=>, 'kredit'?=>, 'arus'?=>]].
     *
     * @param  array<int, array{kode: string, debit?: string, kredit?: string, arus?: string}>  $lines
     */
    private function journal(string $tanggal, array $lines, string $key): void
    {
        $resolved = array_map(fn ($l) => [
            'akun_id' => $this->akunByKode($l['kode']),
            'debit' => $l['debit'] ?? '0',
            'kredit' => $l['kredit'] ?? '0',
        ], $lines);

        $trx = app(SaveManualJournal::class)->handle([
            'tanggal' => $tanggal,
            'periode_id' => $this->periode->id,
            'keterangan' => 'Jurnal demo',
            'lines' => $resolved,
        ], $this->actor);

        foreach ($lines as $l) {
            if (! empty($l['arus'])) {
                TransaksiDetail::where('transaksi_id', $trx->id)
                    ->where('akun_id', $this->akunByKode($l['kode']))
                    ->update(['kelompok_arus_kas' => $l['arus']]);
            }
        }

        app(PostingService::class)->post($trx->fresh(['details']), $trx->version, $this->actor, $key, 'demo');
    }
}
