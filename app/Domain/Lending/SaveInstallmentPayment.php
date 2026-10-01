<?php

namespace App\Domain\Lending;

use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\Angsuran;
use App\Models\PembayaranAngsuran;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Draft pembayaran angsuran dengan alokasi pokok/bunga/denda per jadwal
 * (dokumen09 §Kontrak pembayaran angsuran). Unit/pihak diturunkan dari pinjaman.
 * Menolak alokasi lintas pinjaman. Overpayment dicek saat posting (strategy).
 */
class SaveInstallmentPayment
{
    /**
     * @param  array{tanggal: string, akun_kas_id: int, allocations: array<int, array{angsuran_id: int, pokok?: string, bunga?: string, denda?: string}>}  $data
     */
    public function handle(array $data, User $actor): Transaksi
    {
        $angsurans = Angsuran::with('pinjaman')
            ->whereIn('id', array_column($data['allocations'], 'angsuran_id'))
            ->get();

        if ($angsurans->pluck('pinjaman_id')->unique()->count() !== 1) {
            throw DomainException::invalidState('Alokasi pembayaran harus pada satu pinjaman.');
        }

        $pinjaman = $angsurans->first()->pinjaman;

        $periode = PeriodeAkuntansi::whereDate('tanggal_mulai', '<=', $data['tanggal'])
            ->whereDate('tanggal_selesai', '>=', $data['tanggal'])->first();
        if (! $periode) {
            throw DomainException::invalidState('Tidak ada periode akuntansi untuk tanggal pembayaran.');
        }

        $total = '0';
        foreach ($data['allocations'] as $a) {
            $total = Decimal::add($total, Decimal::add(Decimal::add($a['pokok'] ?? 0, $a['bunga'] ?? 0), $a['denda'] ?? 0));
        }

        return DB::transaction(function () use ($data, $actor, $periode, $pinjaman, $total) {
            $trx = Transaksi::create([
                'nomor_transaksi' => 'AG-'.strtoupper((string) Str::ulid()),
                'tanggal' => $data['tanggal'],
                'periode_id' => $periode->id,
                'unit_usaha_id' => $pinjaman->unit_usaha_id,
                'pihak_id' => $pinjaman->peminjam_id,
                'user_id' => $actor->id,
                'jenis_transaksi' => TransaksiJenis::Angsuran->value,
                'status' => TransaksiStatus::Draft->value,
                'akun_kas_id' => $data['akun_kas_id'],
                'total' => $total,
                'request_key' => (string) Str::uuid(),
                'version' => 1,
                'keterangan' => 'Angsuran '.$pinjaman->nomor_pinjaman,
            ]);

            foreach ($data['allocations'] as $a) {
                PembayaranAngsuran::create([
                    'transaksi_id' => $trx->id,
                    'angsuran_id' => $a['angsuran_id'],
                    'pokok' => $a['pokok'] ?? 0,
                    'bunga' => $a['bunga'] ?? 0,
                    'denda' => $a['denda'] ?? 0,
                ]);
            }

            return $trx;
        });
    }
}
