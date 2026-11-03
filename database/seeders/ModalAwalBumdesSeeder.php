<?php

namespace Database\Seeders;

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
use App\Models\MutasiStok;
use App\Models\PeriodeAkuntansi;
use App\Models\Produk;
use App\Models\SaldoAwalAkun;
use App\Models\SaldoAwalRincian;
use App\Models\Transaksi;
use App\Models\UnitUsaha;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Modal awal BUMDes Munggu untuk go-live, sama dengan database lokal: modal
 * barang (stok awal 90 produk, data/modal-awal-2026-11/persediaan.csv) + modal
 * kas, keduanya lawan 3.1.01.00 Modal Desa Munggu, sebagai saldo awal TERKUNCI
 * periode cutover. Nilai diatur di DatabaseSeeder (PERIODE_AWAL, MODAL_KAS,
 * UNIT_KAS, MODAL_BARANG). Stok produk terbentuk saat saldo awal dikunci; tidak
 * ada transaksi — transaksi periode cutover dst. diinput Admin.
 *
 * Aman diulang: bila saldo awal periode cutover sudah dikunci, tidak ada yang
 * diubah. Menolak database yang sudah berisi saldo awal/transaksi/stok lain;
 * seluruh pemuatan dalam satu transaksi (gagal = tidak ada yang tersimpan).
 */
class ModalAwalBumdesSeeder extends Seeder
{
    public const FILE = __DIR__.'/data/modal-awal-2026-11/persediaan.csv';

    private const AKUN_KAS = '1.1.01.01';

    private const AKUN_PERSEDIAAN = '1.1.05.01';

    private const AKUN_MODAL = '3.1.01.00';

    private User $actor;

    public function run(
        string $periode = DatabaseSeeder::PERIODE_AWAL,
        string $modalKas = DatabaseSeeder::MODAL_KAS,
        string $unitKas = DatabaseSeeder::UNIT_KAS,
        string $modalBarang = DatabaseSeeder::MODAL_BARANG,
    ): void {
        [$tahun, $bulan] = array_map('intval', explode('-', $periode));

        $cutover = PeriodeAkuntansi::where('tahun', $tahun)->where('bulan', $bulan)->first();
        if ($cutover?->opening_locked_at !== null) {
            $this->command?->info("Saldo awal {$periode} sudah dimuat & dikunci — tidak diubah.");

            return;
        }

        if (SaldoAwalAkun::exists() || Transaksi::whereNotNull('posted_at')->exists() || MutasiStok::exists()) {
            throw new RuntimeException(
                'Database sudah berisi saldo awal/transaksi/stok. ModalAwalBumdesSeeder hanya untuk database baru '
                .'(php artisan migrate:fresh --force lalu jalankan ulang).'
            );
        }

        $this->actor = User::where('role', UserRole::Admin->value)->where('status', UserStatus::Aktif->value)
            ->orderBy('id')->first()
            ?? throw new RuntimeException('Master data & akun Admin belum ada. Jalankan `php artisan db:seed` (DatabaseSeeder) dengan SEED_ADMIN_* terisi di .env.');

        DB::transaction(function () use ($tahun, $bulan, $modalKas, $unitKas, $modalBarang) {
            $import = app(ImportProducts::class);
            $import->import($import->read(ProdukBumdesSeeder::FILE));

            $mulai = Carbon::create($tahun, $bulan, 1);
            $cutover = PeriodeAkuntansi::firstOrCreate(
                ['tahun' => $tahun, 'bulan' => $bulan],
                [
                    'tanggal_mulai' => $mulai->toDateString(),
                    'tanggal_selesai' => $mulai->copy()->endOfMonth()->toDateString(),
                    'status' => PeriodeStatus::Terbuka->value,
                    'revision' => 0,
                ],
            );

            $persediaan = $this->persediaan();
            $this->commitSaldoAwal($cutover, $persediaan, $modalKas, $unitKas, $modalBarang);
            $this->seedRincian($cutover, $persediaan);

            app(LockOpeningBalance::class)->handle($cutover, $this->actor);
        });

        $this->summary($periode, $modalKas, $modalBarang);
    }

    /** Stok awal per produk + produk & unitnya. @return array<int, array<string, mixed>> */
    private function persediaan(): array
    {
        $handle = fopen(self::FILE, 'r');
        $header = fgetcsv($handle);
        $rows = [];
        while (($r = fgetcsv($handle)) !== false) {
            if ($r === [null]) {
                continue;
            }
            $r = array_combine($header, $r);
            // Spasi dirapikan sama seperti ImportProducts saat master produk dibuat.
            $nama = preg_replace('/\s+/', ' ', trim($r['nama_produk']));
            $produk = Produk::with('unitUsaha')->where('nama_produk', $nama)->first()
                ?? throw new RuntimeException("Produk '{$r['nama_produk']}' dari persediaan.csv tidak ada di master produk.");

            if ($produk->unitUsaha->kode_unit !== $r['unit']) {
                throw new RuntimeException("Produk '{$nama}' di master ber-unit {$produk->unitUsaha->kode_unit}, persediaan.csv menulis {$r['unit']}.");
            }

            $rows[] = $r + ['produk' => $produk];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * Saldo awal per akun & unit: kas + persediaan (dijumlah dari rincian per
     * unit), lawan Modal Desa pada unit yang sama. Divalidasi lewat
     * PreviewImport yang sama dengan menu Saldo Awal.
     */
    private function commitSaldoAwal(PeriodeAkuntansi $periode, array $persediaan, string $modalKas, string $unitKas, string $modalBarang): void
    {
        $stokPerUnit = [];
        foreach ($persediaan as $p) {
            $stokPerUnit[$p['unit']] = Decimal::add($stokPerUnit[$p['unit']] ?? '0', $p['nilai']);
        }

        $totalStok = array_reduce($stokPerUnit, fn ($s, $v) => Decimal::add($s, $v), '0');
        if (Decimal::compare($totalStok, $modalBarang) !== 0) {
            throw new RuntimeException("Total persediaan.csv {$totalStok} ≠ MODAL_BARANG {$modalBarang}.");
        }

        $modalPerUnit = $stokPerUnit;
        $modalPerUnit[$unitKas] = Decimal::add($modalPerUnit[$unitKas] ?? '0', $modalKas);

        $rows = [['kode_akun' => self::AKUN_KAS, 'unit_kode' => $unitKas, 'saldo_debit' => $modalKas, 'saldo_kredit' => '0', 'keterangan' => 'Modal awal kas']];
        foreach ($stokPerUnit as $unit => $nilai) {
            $rows[] = ['kode_akun' => self::AKUN_PERSEDIAAN, 'unit_kode' => $unit, 'saldo_debit' => $nilai, 'saldo_kredit' => '0', 'keterangan' => 'Modal awal barang (stok awal)'];
        }
        foreach ($modalPerUnit as $unit => $nilai) {
            $rows[] = ['kode_akun' => self::AKUN_MODAL, 'unit_kode' => $unit, 'saldo_debit' => '0', 'saldo_kredit' => $nilai, 'keterangan' => 'Modal Desa Munggu'];
        }

        $preview = app(PreviewImport::class)->handle($rows);
        if ($preview['has_errors'] || ! $preview['balanced']) {
            $errors = collect($preview['rows'])->flatMap(fn ($r) => array_map(fn ($e) => "{$r['kode_akun']}: {$e}", $r['errors']))->implode('; ');
            throw new RuntimeException("Saldo awal modal tidak valid/tidak seimbang (debit {$preview['total_debit']}, kredit {$preview['total_kredit']}). {$errors}");
        }

        app(CommitImport::class)->handle($periode, $preview, $this->actor);
    }

    /** Rincian stok per produk agar saldo awal persediaan bisa dikunci & membentuk kartu stok. */
    private function seedRincian(PeriodeAkuntansi $periode, array $persediaan): void
    {
        $akunId = Akun::where('kode_akun', self::AKUN_PERSEDIAAN)->value('id');
        $saldoId = [];

        foreach ($persediaan as $p) {
            $saldoId[$p['unit']] ??= SaldoAwalAkun::where('periode_id', $periode->id)->where('akun_id', $akunId)
                ->where('unit_usaha_id', UnitUsaha::where('kode_unit', $p['unit'])->value('id'))
                ->firstOrFail()->id;

            SaldoAwalRincian::create([
                'saldo_awal_akun_id' => $saldoId[$p['unit']],
                'jenis' => SaldoRincianJenis::Stok->value,
                'kode_referensi' => $p['produk']->kode_produk,
                'produk_id' => $p['produk']->id,
                'tanggal_asal' => $periode->tanggal_mulai->toDateString(),
                'kuantitas' => $p['kuantitas'],
                'harga_pokok' => (string) BigDecimal::of($p['nilai'])->dividedBy($p['kuantitas'], 6, RoundingMode::HALF_UP),
                'nominal' => $p['nilai'],
            ]);
        }
    }

    private function summary(string $periode, string $modalKas, string $modalBarang): void
    {
        if ($this->command === null) {
            return;
        }

        $rp = fn (string $v) => 'Rp '.number_format((float) $v, 0, ',', '.');
        $this->command->newLine();
        $this->command->info("Modal awal BUMDes dimuat sebagai saldo awal {$periode} (terkunci).");
        $this->command->table(['Data', 'Nilai'], [
            ['Produk (berstok awal)', Produk::count().' ('.Produk::where('stok_terkini', '>', 0)->count().')'],
            ['Modal barang (persediaan)', $rp($modalBarang)],
            ['Modal kas', $rp($modalKas)],
            ['Total aktiva = Modal Desa', $rp(Decimal::add($modalKas, $modalBarang))],
        ]);
        $this->command->line("   Lanjutkan: kosongkan SEED_*_PASSWORD di .env, `php artisan config:clear`, lalu Admin mulai mencatat transaksi {$periode}.");
    }
}
