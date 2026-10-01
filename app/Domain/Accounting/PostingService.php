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

/**
 * Posting atomik satu transaksi (dokumen06 §Posting). Satu DB transaction,
 * lock urut periode→sumber, idempotency transition (request_key/hash),
 * optimistic version, plan dihitung ulang & divalidasi seimbang saat post.
 */
class PostingService
{
    public function __construct(
        private JournalPlanRegistry $registry,
        private JournalValidator $validator,
        private PeriodGuard $periodGuard,
        private AuditWriter $audit,
    ) {}

    public function post(Transaksi $transaksi, int $expectedVersion, User $actor, string $requestKey, string $requestHash): Transaksi
    {
        return DB::transaction(function () use ($transaksi, $expectedVersion, $actor, $requestKey, $requestHash) {
            // Idempotency transition (dokumen05): key sama+hash sama → replay hasil; hash beda → konflik.
            $existing = LogAktivitas::where('request_key', $requestKey)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->request_hash === $requestHash) {
                    return Transaksi::findOrFail($existing->result_ref['transaksi_id'] ?? $transaksi->id);
                }
                throw DomainException::idempotencyConflict();
            }

            // Lock urut: sumber lalu periode.
            $trx = Transaksi::whereKey($transaksi->id)->lockForUpdate()->firstOrFail();
            $periode = PeriodeAkuntansi::whereKey($trx->periode_id)->lockForUpdate()->firstOrFail();

            $this->periodGuard->assertPostable($periode);

            if (! $actor->isActive()) {
                throw DomainException::invalidState('Actor tidak aktif.');
            }

            if ($trx->version !== $expectedVersion) {
                throw DomainException::staleVersion();
            }

            if ($trx->status === TransaksiStatus::Posted) {
                // Sudah posted: idempotent no-op (tanpa jurnal baru).
                $this->audit->log($actor->id, 'Accounting', 'post', 'transaksi', $trx->id, null, null, 'Sudah posted (no-op)', $requestKey, $requestHash, ['transaksi_id' => $trx->id]);

                return $trx;
            }

            if ($trx->status === TransaksiStatus::Reversed) {
                throw DomainException::invalidState('Transaksi reversed tidak dapat diposting.');
            }

            // Hitung ulang plan dari data server (strategy mengunci produk/target sendiri).
            $strategy = $this->registry->for($trx);
            $plan = $strategy->plan($trx);
            $this->validator->validate($plan);

            // Tulis jurnal kanonik: hapus baris draft, insert baris plan.
            TransaksiDetail::where('transaksi_id', $trx->id)->delete();
            $nomor = 1;
            foreach ($plan->lines as $line) {
                TransaksiDetail::create([
                    'transaksi_id' => $trx->id,
                    'akun_id' => $line->akunId,
                    'debit' => $line->debit,
                    'kredit' => $line->kredit,
                    'kelompok_arus_kas' => $line->kelompokArusKas,
                    'keterangan' => $line->keterangan,
                    'nomor_baris' => $nomor++,
                ]);
            }

            // Total dokumen dihitung SEBELUM efek stok (agar konsisten dengan
            // plan; applyEffects dapat mengubah state stok yang dipakai compute).
            $documentTotal = $strategy->documentTotal($trx);

            // Efek domain (stok/subledger/cache) dalam transaksi yang sama.
            $strategy->applyEffects($trx);

            $trx->update([
                'total' => $documentTotal,
                'status' => TransaksiStatus::Posted->value,
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'version' => $trx->version + 1,
            ]);

            $periode->increment('revision');

            $this->audit->log($actor->id, 'Accounting', 'post', 'transaksi', $trx->id, null, ['total' => $plan->total()], null, $requestKey, $requestHash, ['transaksi_id' => $trx->id]);

            return $trx->refresh();
        });
    }
}
