<?php

namespace App\Domain\Evidence;

use App\Enums\VerifikasiStatus;
use App\Models\BuktiTransaksi;
use App\Models\User;

/**
 * Verifikasi/penolakan bukti. Kewenangan (bukan pengunggah sendiri / Pimpinan)
 * ditegakkan via Gate 'verify-evidence' sebelum memanggil action ini.
 */
class VerifyEvidence
{
    public function verify(BuktiTransaksi $bukti, User $actor): void
    {
        $bukti->update([
            'status_verifikasi' => VerifikasiStatus::Valid->value,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function reject(BuktiTransaksi $bukti, User $actor, string $reason): void
    {
        $bukti->update([
            'status_verifikasi' => VerifikasiStatus::Ditolak->value,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
