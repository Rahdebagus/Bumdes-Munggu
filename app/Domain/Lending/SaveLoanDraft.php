<?php

namespace App\Domain\Lending;

use App\Enums\AngsuranStatus;
use App\Enums\PinjamanStatus;
use App\Models\Angsuran;
use App\Models\Pinjaman;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Draft kontrak pinjaman + jadwal angsuran (dokumen08 §5). basis_bunga wajib.
 * sisa_pokok awal = pokok; dikurangi dari histori pembayaran saat dibayar.
 */
class SaveLoanDraft
{
    public function __construct(private ScheduleCalculator $calculator) {}

    /**
     * @param  array{tanggal_pinjaman: string, unit_usaha_id: int, peminjam_id: int, pokok_pinjaman: string, persentase_bunga: string, tenor: int, basis_bunga: string, tanggal_angsuran_pertama: string, keterangan?: ?string}  $data
     */
    public function handle(array $data, User $actor): Pinjaman
    {
        $totalBunga = $this->calculator->totalBunga(
            $data['pokok_pinjaman'], $data['persentase_bunga'], $data['tenor'], $data['basis_bunga']
        );

        return DB::transaction(function () use ($data, $totalBunga) {
            $pinjaman = Pinjaman::create([
                'nomor_pinjaman' => 'PJ-'.strtoupper((string) Str::ulid()),
                'unit_usaha_id' => $data['unit_usaha_id'],
                'peminjam_id' => $data['peminjam_id'],
                'tanggal_pinjaman' => $data['tanggal_pinjaman'],
                'pokok_pinjaman' => $data['pokok_pinjaman'],
                'persentase_bunga' => $data['persentase_bunga'],
                'tenor' => $data['tenor'],
                'total_bunga' => $totalBunga,
                'sisa_pokok' => $data['pokok_pinjaman'],
                'status' => PinjamanStatus::Draft->value,
                'basis_bunga' => $data['basis_bunga'],
                'tanggal_angsuran_pertama' => $data['tanggal_angsuran_pertama'],
            ]);

            foreach ($this->calculator->build(
                $data['pokok_pinjaman'], $data['persentase_bunga'], $data['tenor'],
                $data['basis_bunga'], $data['tanggal_angsuran_pertama']
            ) as $row) {
                Angsuran::create([
                    'pinjaman_id' => $pinjaman->id,
                    'angsuran_ke' => $row['angsuran_ke'],
                    'tanggal_jatuh_tempo' => $row['tanggal_jatuh_tempo'],
                    'pokok_tagihan' => $row['pokok_tagihan'],
                    'bunga_tagihan' => $row['bunga_tagihan'],
                    'denda_tagihan' => 0,
                    'status' => AngsuranStatus::BelumDibayar->value,
                    'version' => 1,
                ]);
            }

            return $pinjaman;
        });
    }
}
