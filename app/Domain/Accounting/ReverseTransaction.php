<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\DomainException;
use App\Enums\TransaksiStatus;
use App\Models\LogAktivitas;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membalik transaksi posted (dokumen06 §Reverse). Alasan wajib, tanggal pembalik
 * pada periode terbuka. Baris jurnal disalin dengan debit/kredit tertukar dan
 * ditautkan reversal_of_id. Original menjadi reversed tetapi posted_at-nya tetap
 * sehingga ledger tetap menghitungnya pada tanggal historisnya.
 */
class ReverseTransaction
{
    public function __construct(
        private PeriodGuard $periodGuard,
        private AuditWriter $audit,
    ) {}

    public function reverse(Transaksi $original, string $reversalDate, string $reason, User $actor, string $requestKey, string $requestHash): Transaksi
    {
        return DB::transaction(function () use ($original, $reversalDate, $reason, $actor, $requestKey, $requestHash) {
            $existing = LogAktivitas::where('request_key', $requestKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->request_hash === $requestHash) {
                    return Transaksi::findOrFail($existing->result_ref['transaksi_id'] ?? $original->id);
                }
                throw DomainException::idempotencyConflict();
            }

            if (trim($reason) === '') {
                throw DomainException::invalidState('Alasan pembalikan wajib diisi.');
            }

            $orig = Transaksi::whereKey($original->id)->lockForUpdate()->firstOrFail();

            if ($orig->status !== TransaksiStatus::Posted) {
                throw DomainException::invalidState('Hanya transaksi posted yang dapat dibalik.');
            }

            if (Transaksi::where('reversal_of_id', $orig->id)->exists()) {
                throw DomainException::reverseDependency('Transaksi sudah memiliki pembalik.');
            }

            $periode = PeriodeAkuntansi::whereDate('tanggal_mulai', '<=', $reversalDate)
                ->whereDate('tanggal_selesai', '>=', $reversalDate)
                ->lockForUpdate()
                ->first();

            if (! $periode) {
                throw DomainException::invalidState('Periode untuk tanggal pembalik tidak ditemukan.');
            }

            $this->periodGuard->assertPostable($periode);

            $reversal = Transaksi::create([
                'nomor_transaksi' => 'REV-'.$orig->id,
                'tanggal' => $reversalDate,
                'periode_id' => $periode->id,
                'unit_usaha_id' => $orig->unit_usaha_id,
                'pihak_id' => $orig->pihak_id,
                'user_id' => $actor->id,
                'jenis_transaksi' => $orig->jenis_transaksi->value,
                'total' => $orig->total,
                'status' => TransaksiStatus::Posted->value,
                'reversal_of_id' => $orig->id,
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'request_key' => (string) Str::uuid(),
                'version' => 1,
                'reversal_reason' => $reason,
                'reversal_date' => $reversalDate,
                'keterangan' => 'Pembalik '.$orig->nomor_transaksi,
            ]);

            $nomor = 1;
            foreach ($orig->details()->orderBy('nomor_baris')->get() as $d) {
                TransaksiDetail::create([
                    'transaksi_id' => $reversal->id,
                    'akun_id' => $d->akun_id,
                    'debit' => $d->kredit,   // tertukar
                    'kredit' => $d->debit,   // tertukar
                    'kelompok_arus_kas' => $d->kelompok_arus_kas,
                    'keterangan' => 'Pembalik: '.($d->keterangan ?? ''),
                    'nomor_baris' => $nomor++,
                ]);
            }

            // Original menjadi reversed; posted_at TIDAK diubah.
            $orig->update(['status' => TransaksiStatus::Reversed->value]);

            $periode->increment('revision');

            $this->audit->log($actor->id, 'Accounting', 'reverse', 'transaksi', $orig->id, null, ['reversal_id' => $reversal->id], $reason, $requestKey, $requestHash, ['transaksi_id' => $reversal->id]);

            return $reversal;
        });
    }
}
