<?php

namespace App\Domain\Accounting;

use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat draft jurnal penyesuaian (manual) beserta barisnya. Draft belum masuk
 * ledger; efek finansial baru terjadi saat PostingService::post.
 */
class SaveManualJournal
{
    /**
     * @param  array{tanggal: string, periode_id: int, keterangan?: ?string, lines: array<int, array{akun_id: int, debit?: string|float, kredit?: string|float, keterangan?: ?string}>}  $data
     */
    public function handle(array $data, User $actor): Transaksi
    {
        return DB::transaction(function () use ($data, $actor) {
            $trx = Transaksi::create([
                'nomor_transaksi' => 'JP-'.strtoupper((string) Str::ulid()),
                'tanggal' => $data['tanggal'],
                'periode_id' => $data['periode_id'],
                'user_id' => $actor->id,
                'jenis_transaksi' => TransaksiJenis::Penyesuaian->value,
                'status' => TransaksiStatus::Draft->value,
                'total' => 0,
                'request_key' => (string) Str::uuid(),
                'version' => 1,
                'keterangan' => $data['keterangan'] ?? null,
            ]);

            $nomor = 1;
            foreach ($data['lines'] as $line) {
                TransaksiDetail::create([
                    'transaksi_id' => $trx->id,
                    'akun_id' => $line['akun_id'],
                    'debit' => $line['debit'] ?? 0,
                    'kredit' => $line['kredit'] ?? 0,
                    'keterangan' => $line['keterangan'] ?? null,
                    'nomor_baris' => $nomor++,
                ]);
            }

            return $trx;
        });
    }
}
