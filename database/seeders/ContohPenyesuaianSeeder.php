<?php

namespace Database\Seeders;

use App\Domain\Accounting\SaveManualJournal;
use App\Domain\Cash\SaveCashDraft;
use App\Domain\Reporting\ReportService;
use App\Domain\Shared\Decimal;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Akun;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Contoh Jurnal Penyesuaian akhir bulan untuk latihan/UAT. Hanya membuat DRAFT
 * pada periode terbuka terakhir — buku tidak berubah sampai Admin menekan
 * Posting (menu Jurnal Penyesuaian / Daftar Transaksi); draft yang tidak dipakai
 * dapat dihapus dari Daftar Transaksi. Menolak production; idempoten per
 * keterangan (aman dijalankan ulang).
 *
 *   php artisan db:seed --class=ContohPenyesuaianSeeder
 *
 * 1. Akrual gaji    : D 6.1.1 Biaya Pegawai 1.500.000 / K 2.1.00.02 Utang Gaji.
 *                     Dibayar bulan berikutnya lewat Pengeluaran Kas → Biaya Pegawai,
 *                     akun lawan Utang Gaji (D Utang Gaji / K Kas).
 * 2. Koreksi akun   : draft kas "beli bensin" 150.000 yang salah dicatat ke 6.1.3 Biaya
 *                     Kantor, lalu D 6.1.2 Biaya Transport / K 6.1.3 Biaya Kantor 150.000.
 *                     Posting keduanya → Biaya Kantor kembali 0, Biaya Transport 150.000.
 * 3. Taksiran pajak : D 8.0 Pajak / K 2.1.00.03 Utang Pajak = 0,5% × omzet desa periode
 *                     (contoh tarif PPh final; dilewati bila omzet nol).
 */
class ContohPenyesuaianSeeder extends Seeder
{
    public const PREFIX = '[CONTOH]';

    public const GAJI = '1500000';

    public const BENSIN = '150000';

    public const TARIF_PAJAK = '0.005';

    private User $actor;

    private PeriodeAkuntansi $periode;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('ContohPenyesuaianSeeder tidak boleh dijalankan di production.');
        }

        $this->actor = User::where('role', UserRole::Admin->value)->where('status', UserStatus::Aktif->value)->orderBy('id')->first()
            ?? throw new RuntimeException('Belum ada user Admin aktif.');
        $this->periode = PeriodeAkuntansi::where('status', 'terbuka')->orderByDesc('tanggal_mulai')->first()
            ?? throw new RuntimeException('Belum ada periode terbuka.');

        $from = $this->periode->tanggal_mulai->toDateString();
        $akhir = $this->periode->tanggal_selesai->toDateString();

        // 1. Akrual gaji.
        $this->journal('Akrual gaji pegawai bulan ini — dibayar awal bulan depan', $akhir, [
            ['6.1.1', self::GAJI, '0', 'Biaya gaji bulan ini'],
            ['2.1.00.02', '0', self::GAJI, 'Gaji terutang'],
        ]);

        // 2. Salah catat (draft kas) + koreksinya.
        $salah = self::PREFIX.' Beli bensin motor antar pesanan (salah akun: Biaya Kantor)';
        if (! $this->exists($salah)) {
            app(SaveCashDraft::class)->handle([
                'tanggal' => $this->periode->tanggal_mulai->copy()->addDays(14)->toDateString(),
                'kategori_kode' => 'biaya_kantor',
                'akun_kas_id' => $this->akun('1.1.01.01'),
                'akun_lawan_id' => $this->akun('6.1.3'),
                'nominal' => self::BENSIN,
                'keterangan' => $salah,
            ], $this->actor);
        }
        $this->journal('Koreksi salah akun — bensin dipindah dari Biaya Kantor ke Biaya Transport', $akhir, [
            ['6.1.2', self::BENSIN, '0', 'Seharusnya Biaya Transport'],
            ['6.1.3', '0', self::BENSIN, 'Batalkan pencatatan di Biaya Kantor'],
        ]);

        // 3. Taksiran pajak dari omzet terposting saat seeder dijalankan.
        $omzet = app(ReportService::class)->omzetDesa($from, $akhir);
        $pajak = Decimal::of(Decimal::mul($omzet, self::TARIF_PAJAK, 0));
        if (Decimal::isPositive($pajak)) {
            $dasar = 'Dasar omzet '.number_format((float) $omzet, 0, ',', '.');
            $this->journal('Taksiran PPh final 0,5% dari omzet periode', $akhir, [
                ['8.0', $pajak, '0', $dasar],
                ['2.1.00.03', '0', $pajak, 'Pajak terutang, disetor bulan depan'],
            ]);
        } else {
            $this->command?->warn('Omzet periode masih nol — contoh taksiran pajak dilewati.');
        }

        $this->command?->info("Contoh penyesuaian (draft) dibuat di periode {$this->periode->bulan}/{$this->periode->tahun}. Posting dari menu Jurnal Penyesuaian & Daftar Transaksi.");
    }

    /** @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $lines  [kode akun, debit, kredit, keterangan] */
    private function journal(string $keterangan, string $tanggal, array $lines): void
    {
        $keterangan = self::PREFIX.' '.$keterangan;
        if ($this->exists($keterangan)) {
            return;
        }

        app(SaveManualJournal::class)->handle([
            'tanggal' => $tanggal,
            'periode_id' => $this->periode->id,
            'keterangan' => $keterangan,
            'lines' => array_map(fn ($l) => [
                'akun_id' => $this->akun($l[0]), 'debit' => $l[1], 'kredit' => $l[2], 'keterangan' => $l[3],
            ], $lines),
        ], $this->actor);
    }

    private function exists(string $keterangan): bool
    {
        return Transaksi::where('periode_id', $this->periode->id)->where('keterangan', $keterangan)->exists();
    }

    private function akun(string $kode): int
    {
        return (int) (Akun::where('kode_akun', $kode)->value('id')
            ?? throw new RuntimeException("Akun {$kode} tidak ditemukan; jalankan MasterDataSeeder."));
    }
}
