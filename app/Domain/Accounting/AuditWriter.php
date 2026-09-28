<?php

namespace App\Domain\Accounting;

use App\Models\LogAktivitas;

/**
 * Menulis log aktivitas append-only. Tidak menyimpan password/token/isi
 * dokumen sensitif (dokumen04 §16).
 */
class AuditWriter
{
    /**
     * @param  array<string, mixed>|null  $nilaiLama
     * @param  array<string, mixed>|null  $nilaiBaru
     * @param  array<string, mixed>|null  $resultRef
     */
    public function log(
        int $userId,
        string $modul,
        string $aksi,
        ?string $referensiTipe = null,
        ?int $referensiId = null,
        ?array $nilaiLama = null,
        ?array $nilaiBaru = null,
        ?string $alasan = null,
        ?string $requestKey = null,
        ?string $requestHash = null,
        ?array $resultRef = null,
    ): LogAktivitas {
        return LogAktivitas::create([
            'user_id' => $userId,
            'modul' => $modul,
            'aksi' => $aksi,
            'referensi_tipe' => $referensiTipe,
            'referensi_id' => $referensiId,
            'nilai_lama' => $nilaiLama,
            'nilai_baru' => $nilaiBaru,
            'alasan' => $alasan,
            'request_key' => $requestKey,
            'request_hash' => $requestHash,
            'result_ref' => $resultRef,
        ]);
    }
}
