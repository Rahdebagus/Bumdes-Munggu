<?php

namespace Tests\Feature\Reporting\Concerns;

use App\Domain\Accounting\PostingService;
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
use App\Models\UnitUsaha;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;

/**
 * Pembuat Fixture A (dokumen11) lewat Actions aplikasi, dipakai bersama oleh
 * uji penerimaan laporan, ekspor, dan UI agar angka konsisten lintas keluaran.
 */
trait BuildsFixtureA
{
    protected User $actor;

    protected PeriodeAkuntansi $periode;

    protected UnitUsaha $dagang;

    protected UnitUsaha $kredit;

    protected Produk $produk;

    protected Pihak $peminjam;

    protected const FROM = '2026-01-01';

    protected const TO = '2026-01-31';

    protected function bootFixtureA(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->actor = User::factory()->create();
        $this->periode = PeriodeAkuntansi::factory()->bulan(1)->create(['status' => 'terbuka']);
        $this->dagang = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->firstOrFail();
        $this->kredit = UnitUsaha::where('kode_unit', 'UNIT-KREDIT')->firstOrFail();
        $this->produk = Produk::factory()->create([
            'unit_usaha_id' => $this->dagang->id, 'stok_terkini' => 0, 'harga_pokok_rata' => 0,
        ]);
        $this->peminjam = Pihak::factory()->peminjam()->create();

        $this->lockOpening();
    }

    protected function cash(): int
    {
        return (int) Akun::where('system_key', 'cash')->value('id');
    }

    protected function akunId(string $key): int
    {
        return (int) Akun::where('system_key', $key)->value('id');
    }

    private function lockOpening(): void
    {
        $rows = [
            ['kode_akun' => '1-1010', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '8000000', 'saldo_kredit' => '0'],
            ['kode_akun' => '3-3010', 'unit_kode' => 'UNIT-DAGANG', 'saldo_debit' => '0', 'saldo_kredit' => '8000000'],
            ['kode_akun' => '1-1010', 'unit_kode' => 'UNIT-KREDIT', 'saldo_debit' => '2000000', 'saldo_kredit' => '0'],
            ['kode_akun' => '3-3010', 'unit_kode' => 'UNIT-KREDIT', 'saldo_debit' => '0', 'saldo_kredit' => '2000000'],
        ];
        $full = array_map(fn ($r) => array_merge(['keterangan' => ''], $r), $rows);

        (new CommitImport)->handle($this->periode, (new PreviewImport)->handle($full), $this->actor);
        app(LockOpeningBalance::class)->handle($this->periode, $this->actor);
    }

    private function buy(string $tanggal, string $qty, string $harga, string $metode): Transaksi
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal, 'unit_usaha_id' => $this->dagang->id, 'pihak_id' => null, 'metode_pembayaran' => $metode,
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => $qty, 'harga' => $harga, 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Pembelian, $this->actor);

        return app(PostingService::class)->post($draft, 1, $this->actor, 'buy-'.$draft->id, 'h');
    }

    private function sell(string $tanggal, string $qty, string $harga, string $metode): Transaksi
    {
        $draft = app(SaveTradingDraft::class)->handle([
            'tanggal' => $tanggal, 'unit_usaha_id' => $this->dagang->id, 'pihak_id' => null, 'metode_pembayaran' => $metode,
            'items' => [['produk_id' => $this->produk->id, 'kuantitas' => $qty, 'harga' => $harga, 'diskon' => '0', 'pajak' => '0']],
        ], TransaksiJenis::Penjualan, $this->actor);

        return app(PostingService::class)->post($draft, 1, $this->actor, 'sell-'.$draft->id, 'h');
    }

    private function cashDraft(array $data, string $key): Transaksi
    {
        $draft = app(SaveCashDraft::class)->handle($data, $this->actor);

        return app(PostingService::class)->post($draft, 1, $this->actor, $key, 'h');
    }

    protected function runFixture(): void
    {
        $this->buy('2026-01-02', '10', '100000', 'tunai');
        $this->sell('2026-01-03', '2', '150000', 'tunai');
        $sale3 = $this->sell('2026-01-04', '3', '150000', 'kredit');
        $this->cashDraft([
            'tanggal' => '2026-01-05', 'unit_usaha_id' => $this->dagang->id, 'pihak_id' => $sale3->pihak_id,
            'kategori_kode' => 'pelunasan_piutang', 'akun_kas_id' => $this->cash(),
            'allocations' => [['invoice_transaksi_id' => $sale3->id, 'nominal' => '200000']],
        ], 'settle-recv');
        $buy5 = $this->buy('2026-01-06', '4', '125000', 'kredit');
        $this->cashDraft([
            'tanggal' => '2026-01-07', 'unit_usaha_id' => $this->dagang->id, 'pihak_id' => $buy5->pihak_id,
            'kategori_kode' => 'pelunasan_utang', 'akun_kas_id' => $this->cash(),
            'allocations' => [['invoice_transaksi_id' => $buy5->id, 'nominal' => '100000']],
        ], 'settle-pay');
        $loan = app(SaveLoanDraft::class)->handle([
            'tanggal_pinjaman' => '2026-01-08', 'unit_usaha_id' => $this->kredit->id, 'peminjam_id' => $this->peminjam->id,
            'pokok_pinjaman' => '1200000', 'persentase_bunga' => '12', 'tenor' => 12,
            'basis_bunga' => 'flat_total', 'tanggal_angsuran_pertama' => '2026-02-01',
        ], $this->actor);
        app(DisburseLoan::class)->handle($loan, '2026-01-08', $this->cash(), $this->actor, 'disb-'.$loan->id, 'h');
        $angsuran1 = $loan->angsuran()->where('angsuran_ke', 1)->firstOrFail();
        $payDraft = app(SaveInstallmentPayment::class)->handle([
            'tanggal' => '2026-01-09', 'akun_kas_id' => $this->cash(),
            'allocations' => [['angsuran_id' => $angsuran1->id, 'pokok' => '100000', 'bunga' => '12000', 'denda' => '0']],
        ], $this->actor);
        app(PostingService::class)->post($payDraft, 1, $this->actor, 'pay-'.$payDraft->id, 'h');
        $this->cashDraft([
            'tanggal' => '2026-01-10', 'unit_usaha_id' => $this->dagang->id,
            'kategori_kode' => 'beban', 'akun_kas_id' => $this->cash(),
            'akun_lawan_id' => $this->akunId('operating_expense'), 'nominal' => '50000',
        ], 'beban');
        $this->cashDraft([
            'tanggal' => '2026-01-11', 'unit_usaha_id' => $this->dagang->id,
            'kategori_kode' => 'perolehan_aset', 'akun_kas_id' => $this->cash(),
            'akun_lawan_id' => $this->akunId('fixed_asset'), 'nominal' => '1200000',
        ], 'asset-buy');
        $aset = app(RegisterAsset::class)->handle([
            'kode_aset' => 'AST-FA-001', 'unit_usaha_id' => $this->dagang->id, 'nama_aset' => 'Peralatan',
            'tanggal_perolehan' => '2026-01-11', 'harga_perolehan' => '1200000', 'nilai_residu' => '0',
            'umur_manfaat' => 12, 'tanggal_mulai_susut' => '2026-01-31',
        ], $this->actor);
        app(PostDepreciation::class)->handle($aset, $this->periode, $this->actor, 'dep-1', 'h');
    }
}
