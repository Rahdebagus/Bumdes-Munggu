<?php

namespace Database\Seeders;

use App\Domain\FixedAssets\RegisterAsset;
use App\Domain\MasterData\ImportProducts;
use App\Domain\OpeningBalance\CommitImport;
use App\Domain\OpeningBalance\LockOpeningBalance;
use App\Domain\OpeningBalance\PreviewImport;
use App\Domain\Shared\Decimal;
use App\Enums\PeriodeStatus;
use App\Enums\SaldoRincianJenis;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Akun;
use App\Models\AsetTetap;
use App\Models\MutasiStok;
use App\Models\PeriodeAkuntansi;
use App\Models\Pihak;
use App\Models\Produk;
use App\Models\SaldoAwalAkun;
use App\Models\SaldoAwalRincian;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data nyata BUMDes Munggu: neraca 30 Juni 2026 dari laporan keuangan Excel
 * (database/seeders/data/bumdes-2026-06) sebagai saldo awal TERKUNCI periode
 * go-live Juli 2026. ALTERNATIF dari ModalAwalBumdesSeeder (yang dipakai
 * DatabaseSeeder) — dipanggil eksplisit setelah master data & akun login:
 *
 *   php artisan migrate --force
 *   php artisan db:seed --class=ProductionSeeder --force
 *   php artisan db:seed --class=ProduksiBumdesSeeder --force
 *
 * Isi: 159 produk, pelanggan per bidang desa, nasabah pinjaman pedagang
 * pantai, aset tetap, lalu saldo awal + rincian akun kontrol (stok per produk,
 * nota piutang, sisa pinjaman, aset & akumulasinya). Stok awal produk terbentuk
 * saat saldo awal dikunci. Tidak ada transaksi fiktif — transaksi Juli dst.
 * diinput Admin.
 *
 * Aman diulang: bila saldo awal Juli 2026 sudah dikunci, tidak ada yang
 * diubah. Menolak berjalan bila database sudah berisi saldo awal atau posting
 * lain; seluruh pemuatan dalam satu transaksi (gagal = tidak ada yang
 * tersimpan).
 */
class ProduksiBumdesSeeder extends Seeder
{
    public const DIR = __DIR__.'/data/bumdes-2026-06';

    public const TAHUN = 2026;

    public const BULAN = 7;

    /** Tanggal neraca sumber (stok, nota, dan pinjaman per tanggal ini). */
    public const TANGGAL_NERACA = '2026-06-30';

    /** Total aktiva = total pasiva neraca Excel 30 Juni 2026. */
    public const TOTAL_NERACA = '1215728153.78';

    private User $actor;

    public function run(): void
    {
        $periode = PeriodeAkuntansi::where('tahun', self::TAHUN)->where('bulan', self::BULAN)->first();
        if ($periode && $periode->opening_locked_at !== null) {
            $this->command?->info('Saldo awal Juli 2026 sudah dimuat & dikunci — tidak diubah.');

            return;
        }

        if (SaldoAwalAkun::exists() || Transaksi::whereNotNull('posted_at')->exists() || MutasiStok::exists()) {
            throw new RuntimeException(
                'Database sudah berisi saldo awal/transaksi/stok. ProduksiBumdesSeeder hanya untuk database baru '
                .'(php artisan migrate:fresh --force lalu jalankan ulang).'
            );
        }

        $this->actor = User::where('role', UserRole::Admin->value)->where('status', UserStatus::Aktif->value)
            ->orderBy('id')->first()
            ?? throw new RuntimeException('Master data & akun Admin belum ada. Jalankan `php artisan db:seed` (DatabaseSeeder) dengan SEED_ADMIN_* terisi di .env.');

        DB::transaction(function () {
            $import = app(ImportProducts::class);
            $import->import($import->read(ProdukBumdesSeeder::FILE));

            $periode = PeriodeAkuntansi::firstOrCreate(
                ['tahun' => self::TAHUN, 'bulan' => self::BULAN],
                ['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-07-31', 'status' => PeriodeStatus::Terbuka->value, 'revision' => 0],
            );

            $persediaan = $this->persediaan();
            $piutang = $this->csv('piutang_usaha.csv');
            $pinjaman = $this->csv('pinjaman_pedagang.csv');
            $aset = $this->registerAssets();

            $this->commitSaldoAwal($periode, $persediaan, $piutang, $pinjaman, $aset);
            $this->seedRincian($periode, $persediaan, $piutang, $pinjaman, $aset);

            app(LockOpeningBalance::class)->handle($periode, $this->actor);
        });

        $this->summary();
    }

    /** @return array<int, array<string, string>> */
    private function csv(string $file): array
    {
        $handle = fopen(self::DIR.'/'.$file, 'r');
        $header = fgetcsv($handle);
        $rows = [];
        while (($r = fgetcsv($handle)) !== false) {
            if ($r !== [null]) {
                $rows[] = array_combine($header, $r);
            }
        }
        fclose($handle);

        return $rows;
    }

    /** Sisa stok per produk + produk & unitnya. @return array<int, array<string, mixed>> */
    private function persediaan(): array
    {
        return array_map(function ($r) {
            // Spasi dirapikan sama seperti ImportProducts saat master produk dibuat.
            $nama = preg_replace('/\s+/', ' ', trim($r['nama_produk']));
            $produk = Produk::with('unitUsaha')->where('nama_produk', $nama)->first()
                ?? throw new RuntimeException("Produk '{$r['nama_produk']}' dari persediaan.csv tidak ada di master produk.");

            return $r + ['produk' => $produk];
        }, $this->csv('persediaan.csv'));
    }

    /**
     * Aset tetap migrasi (akumulasi s/d Juni 2026 sebagai akumulasi_awal).
     * Inventaris memakai akun default (system_key); aktiva memakai akun
     * Aktiva Tetap Usaha beserta akumulasinya.
     *
     * @return array<int, array<string, mixed>>
     */
    private function registerAssets(): array
    {
        $akun = fn (string $kode) => (int) Akun::where('kode_akun', $kode)->value('id');
        $beban = (int) Akun::where('system_key', 'depreciation_expense')->value('id');

        return array_map(function ($r) use ($akun, $beban) {
            $aktiva = $r['kelompok'] === 'aktiva';
            $aset = AsetTetap::where('kode_aset', $r['kode'])->first() ?? app(RegisterAsset::class)->handle([
                'kode_aset' => $r['kode'],
                'nama_aset' => $r['unit'] > 1 ? "{$r['nama']} ({$r['unit']} unit)" : $r['nama'],
                'tanggal_perolehan' => $r['tanggal_perolehan'],
                'harga_perolehan' => $r['harga_perolehan'],
                'nilai_residu' => '0',
                'umur_manfaat' => (int) $r['umur_bulan'],
                'tanggal_mulai_susut' => $r['tanggal_perolehan'],
                'akumulasi_awal' => $r['akumulasi'],
                'akun_aset_id' => $aktiva ? $akun('1.3.04.01') : null,
                'akun_akumulasi_id' => $aktiva ? $akun('1.3.08.01') : null,
                'akun_beban_id' => $aktiva ? $beban : null,
            ], $this->actor);

            return $r + ['aset' => $aset];
        }, $this->csv('aset_tetap.csv'));
    }

    /**
     * Saldo awal per akun: baris neraca (saldo_awal.csv) + akun kontrol yang
     * nilainya dijumlah dari rincian (persediaan per unit, piutang, pinjaman,
     * inventaris). Divalidasi lewat PreviewImport yang sama dengan menu Saldo Awal.
     */
    private function commitSaldoAwal(PeriodeAkuntansi $periode, array $persediaan, array $piutang, array $pinjaman, array $aset): void
    {
        // Akun ekuitas yang default-nya nonaktif ikut bersaldo di neraca BUMDes.
        Akun::whereIn('kode_akun', array_column($this->csv('saldo_awal.csv'), 'kode_akun'))->update(['is_active' => true]);

        $rows = $this->csv('saldo_awal.csv');
        $sum = fn (array $list, string $col) => array_reduce($list, fn ($s, $r) => Decimal::add($s, $r[$col]), '0');

        $perUnit = [];
        foreach ($persediaan as $p) {
            $kode = $p['produk']->unitUsaha->kode_unit;
            $perUnit[$kode] = Decimal::add($perUnit[$kode] ?? '0', $p['nilai']);
        }
        foreach ($perUnit as $unit => $nilai) {
            $rows[] = ['kode_akun' => '1.1.05.01', 'unit_kode' => $unit, 'saldo_debit' => $nilai, 'saldo_kredit' => '0', 'keterangan' => 'Persediaan barang dagang per 30 Juni 2026'];
        }

        $inventaris = array_values(array_filter($aset, fn ($a) => $a['kelompok'] === 'inventaris'));
        $rows[] = ['kode_akun' => '1.1.03.01', 'unit_kode' => '', 'saldo_debit' => $sum($piutang, 'jumlah'), 'saldo_kredit' => '0', 'keterangan' => 'Piutang usaha per 30 Juni 2026 ('.count($piutang).' nota)'];
        $rows[] = ['kode_akun' => '1.1.03.03', 'unit_kode' => 'UNIT-KREDIT', 'saldo_debit' => $sum($pinjaman, 'sisa_pokok'), 'saldo_kredit' => '0', 'keterangan' => 'Piutang pedagang pantai per 30 Juni 2026 ('.count($pinjaman).' nasabah)'];
        $rows[] = ['kode_akun' => '1.3.03.01', 'unit_kode' => '', 'saldo_debit' => $sum($inventaris, 'harga_perolehan'), 'saldo_kredit' => '0', 'keterangan' => 'Inventaris kantor'];
        $rows[] = ['kode_akun' => '1.3.07.01', 'unit_kode' => '', 'saldo_debit' => '0', 'saldo_kredit' => $sum($inventaris, 'akumulasi'), 'keterangan' => 'Akumulasi penyusutan inventaris s/d Juni 2026'];

        $preview = app(PreviewImport::class)->handle($rows);
        if ($preview['has_errors'] || ! $preview['balanced']) {
            $errors = collect($preview['rows'])->flatMap(fn ($r) => array_map(fn ($e) => "{$r['kode_akun']}: {$e}", $r['errors']))->implode('; ');
            throw new RuntimeException("Saldo awal Excel tidak valid/tidak seimbang (debit {$preview['total_debit']}, kredit {$preview['total_kredit']}). {$errors}");
        }

        // Total aktiva (debit − akumulasi penyusutan) harus sama dengan neraca Excel.
        $totalAktiva = Decimal::sub($preview['total_debit'], $sum($aset, 'akumulasi'));
        if (Decimal::compare($totalAktiva, self::TOTAL_NERACA) !== 0) {
            throw new RuntimeException("Total aktiva {$totalAktiva} ≠ neraca Excel ".self::TOTAL_NERACA.'.');
        }

        app(CommitImport::class)->handle($periode, $preview, $this->actor);
    }

    /** Rincian akun kontrol agar saldo awal bisa dikunci & ditagih per nota/nasabah. */
    private function seedRincian(PeriodeAkuntansi $periode, array $persediaan, array $piutang, array $pinjaman, array $aset): void
    {
        $saldo = fn (string $kode, ?string $unit = null) => SaldoAwalAkun::where('periode_id', $periode->id)
            ->where('akun_id', Akun::where('kode_akun', $kode)->value('id'))
            ->where('unit_usaha_id', $unit ? UnitUsaha::where('kode_unit', $unit)->value('id') : null)
            ->firstOrFail();

        foreach ($persediaan as $p) {
            $produk = $p['produk'];
            SaldoAwalRincian::create([
                'saldo_awal_akun_id' => $saldo('1.1.05.01', $produk->unitUsaha->kode_unit)->id,
                'jenis' => SaldoRincianJenis::Stok->value,
                'kode_referensi' => $produk->kode_produk,
                'produk_id' => $produk->id,
                'tanggal_asal' => self::TANGGAL_NERACA,
                'kuantitas' => $p['kuantitas'],
                'harga_pokok' => (string) BigDecimal::of($p['nilai'])->dividedBy($p['kuantitas'], 6, RoundingMode::HALF_UP),
                'nominal' => $p['nilai'],
            ]);
        }

        $piutangId = $saldo('1.1.03.01')->id;
        foreach ($piutang as $r) {
            SaldoAwalRincian::create([
                'saldo_awal_akun_id' => $piutangId,
                'jenis' => SaldoRincianJenis::PiutangUsaha->value,
                'kode_referensi' => $r['no_nota'],
                'pihak_id' => $this->pihak('pelanggan', 'BDG-'.Str::upper(Str::slug($r['bidang'])), $r['bidang'])->id,
                'tanggal_asal' => $r['tanggal'],
                'nominal' => $r['jumlah'],
                'metadata' => array_filter(['pemesan' => $r['pemesan'], 'uraian' => $r['uraian'], 'keterangan' => $r['keterangan']]),
            ]);
        }

        $pinjamanId = $saldo('1.1.03.03', 'UNIT-KREDIT')->id;
        foreach ($pinjaman as $r) {
            SaldoAwalRincian::create([
                'saldo_awal_akun_id' => $pinjamanId,
                'jenis' => SaldoRincianJenis::Pinjaman->value,
                'kode_referensi' => $r['kode'],
                'pihak_id' => $this->pihak('peminjam', 'PMJ-'.$r['kode'], $r['nama'])->id,
                'tanggal_asal' => $r['tanggal_pinjaman'] ?: null,
                'nominal' => $r['sisa_pokok'],
            ]);
        }

        $asetId = $saldo('1.3.03.01')->id;
        $akumulasiId = $saldo('1.3.07.01')->id;
        foreach ($aset as $r) {
            if ($r['kelompok'] !== 'inventaris') {
                continue; // aktiva: akun tanpa system_key (bukan akun kontrol), tersusut penuh.
            }
            $rincian = SaldoAwalRincian::create([
                'saldo_awal_akun_id' => $asetId, 'jenis' => SaldoRincianJenis::Aset->value,
                'kode_referensi' => $r['kode'], 'aset_tetap_id' => $r['aset']->id,
                'tanggal_asal' => $r['tanggal_perolehan'], 'nominal' => $r['harga_perolehan'],
            ]);
            $r['aset']->update(['opening_rincian_id' => $rincian->id]);

            SaldoAwalRincian::create([
                'saldo_awal_akun_id' => $akumulasiId, 'jenis' => SaldoRincianJenis::AkumulasiAset->value,
                'kode_referensi' => $r['kode'], 'aset_tetap_id' => $r['aset']->id,
                'tanggal_asal' => $r['tanggal_perolehan'], 'nominal' => $r['akumulasi'],
            ]);
        }
    }

    private function pihak(string $jenis, string $kode, string $nama): Pihak
    {
        return Pihak::firstOrCreate(['kode_pihak' => $kode], ['jenis_pihak' => $jenis, 'nama' => $nama, 'status' => 'aktif']);
    }

    private function summary(): void
    {
        if ($this->command === null) {
            return;
        }

        $periode = PeriodeAkuntansi::where('tahun', self::TAHUN)->where('bulan', self::BULAN)->first();
        $this->command->newLine();
        $this->command->info('Data BUMDes per 30 Juni 2026 dimuat sebagai saldo awal Juli 2026 (terkunci).');
        $this->command->table(['Data', 'Jumlah'], [
            ['Produk (berstok awal)', Produk::count().' ('.MutasiStok::count().')'],
            ['Pelanggan per bidang', Pihak::where('jenis_pihak', 'pelanggan')->count()],
            ['Nasabah pinjaman lama', Pihak::where('jenis_pihak', 'peminjam')->count()],
            ['Nota piutang lama', SaldoAwalRincian::where('jenis', SaldoRincianJenis::PiutangUsaha->value)->count()],
            ['Aset tetap', AsetTetap::count()],
            ['Saldo awal dikunci', $periode?->opening_locked_at?->toDateTimeString() ?? '—'],
        ]);
        $this->command->line('   Lanjutkan: kosongkan SEED_*_PASSWORD di .env, `php artisan config:clear`, lalu Admin mulai mencatat transaksi Juli 2026.');
    }
}
