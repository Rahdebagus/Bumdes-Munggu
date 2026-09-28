<?php

namespace App\Domain\OpeningBalance;

use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;
use App\Models\SaldoAwalRincian;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Commit atomik saldo awal ke periode cutover. Menolak bila masih ada error
 * baris atau periode sudah dikunci (proteksi impor ulang). Impor ulang pada
 * periode belum terkunci mengganti seluruh baris opening periode itu.
 */
class CommitImport
{
    /**
     * @param  array{rows: array<int, array<string, mixed>>, has_errors: bool}  $preview
     */
    public function handle(PeriodeAkuntansi $periode, array $preview, User $actor): int
    {
        if ($preview['has_errors']) {
            throw ValidationException::withMessages([
                'import' => 'Masih ada baris bermasalah; perbaiki sebelum commit.',
            ]);
        }

        return DB::transaction(function () use ($periode, $preview, $actor) {
            $locked = PeriodeAkuntansi::whereKey($periode->id)->lockForUpdate()->firstOrFail();

            if ($locked->opening_locked_at !== null) {
                throw ValidationException::withMessages([
                    'import' => 'Saldo awal periode sudah dikunci; impor ulang tidak diizinkan.',
                ]);
            }

            // Impor ulang: bersihkan opening lama (beserta rinciannya) untuk periode ini.
            $existingIds = SaldoAwalAkun::where('periode_id', $periode->id)->pluck('id');
            if ($existingIds->isNotEmpty()) {
                SaldoAwalRincian::whereIn('saldo_awal_akun_id', $existingIds)->delete();
                SaldoAwalAkun::whereIn('id', $existingIds)->delete();
            }

            $batch = (string) Str::uuid();
            $count = 0;
            foreach ($preview['rows'] as $row) {
                SaldoAwalAkun::create([
                    'periode_id' => $periode->id,
                    'unit_usaha_id' => $row['unit_usaha_id'] ?: null,
                    'akun_id' => $row['akun_id'],
                    'saldo_debit' => $row['saldo_debit'],
                    'saldo_kredit' => $row['saldo_kredit'],
                    'keterangan' => $row['keterangan'] ?: null,
                    'created_by' => $actor->id,
                    'source_batch' => $batch,
                ]);
                $count++;
            }

            return $count;
        });
    }
}
