<?php

namespace App\Domain\Period;

use App\Domain\Shared\DomainException;
use App\Enums\PeriodeStatus;
use App\Models\LogAktivitas;
use App\Models\PeriodeAkuntansi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Membuka kembali periode tertutup (dokumen05/dokumen10). Alasan wajib;
 * revision++ agar laporan/snapshot lama terdeteksi usang. Pembalik lintas
 * periode tetap masuk ke periode terbuka pada tanggalnya, bukan mengubah
 * angka periode yang telah ditutup.
 */
class ReopenPeriod
{
    public function handle(PeriodeAkuntansi $periode, User $actor, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw DomainException::invalidState('Alasan membuka kembali periode wajib diisi.');
        }

        DB::transaction(function () use ($periode, $actor, $reason) {
            $p = PeriodeAkuntansi::whereKey($periode->id)->lockForUpdate()->firstOrFail();

            if ($p->status !== PeriodeStatus::Ditutup) {
                throw DomainException::invalidState("Periode {$p->bulan}/{$p->tahun} tidak dalam keadaan tertutup.");
            }

            $p->update([
                'status' => PeriodeStatus::Terbuka->value,
                'reopened_at' => now(),
                'reopened_by' => $actor->id,
                'reopen_reason' => $reason,
                'closed_at' => null,
                'closed_by' => null,
                'revision' => $p->revision + 1,
            ]);

            LogAktivitas::create([
                'user_id' => $actor->id,
                'modul' => 'Periode',
                'aksi' => 'reopen',
                'referensi_tipe' => 'periode_akuntansi',
                'referensi_id' => $p->id,
                'alasan' => $reason,
            ]);
        });
    }
}
