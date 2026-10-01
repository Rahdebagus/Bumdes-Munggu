<?php

namespace App\Domain\Evidence;

use App\Enums\VerifikasiStatus;
use App\Models\BuktiTransaksi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Simpan bukti transaksi ke disk private dengan nama acak, catat metadata +
 * sha256. Validasi MIME/ukuran dilakukan di lapisan komponen (pdf/jpg/png, 5MiB).
 */
class UploadEvidence
{
    public function handle(Transaksi $trx, UploadedFile $file, User $actor, string $jenis = 'nota', ?string $nomor = null): BuktiTransaksi
    {
        // Disk 'local' = storage/app/private (private) pada Laravel 11/12.
        $path = $file->store('bukti', 'local');

        return BuktiTransaksi::create([
            'transaksi_id' => $trx->id,
            'nomor_bukti' => $nomor ?: 'BKT-'.strtoupper((string) Str::random(8)),
            'tanggal_bukti' => now()->toDateString(),
            'jenis_bukti' => $jenis,
            'file_bukti' => $path,
            'status_verifikasi' => VerifikasiStatus::BelumDiperiksa->value,
            'uploaded_by' => $actor->id,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ]);
    }
}
