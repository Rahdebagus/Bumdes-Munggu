<?php

namespace App\Domain\Lending;

use App\Domain\Accounting\PostingService;
use App\Domain\Shared\DomainException;
use App\Enums\PinjamanStatus;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\PeriodeAkuntansi;
use App\Models\Pinjaman;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mencairkan pinjaman draft: membuat transaksi pencairan, menautkannya ke
 * pinjaman, lalu memposting lewat PostingService (status → aktif via strategy).
 */
class DisburseLoan
{
    public function __construct(private PostingService $posting) {}

    public function handle(Pinjaman $pinjaman, string $tanggal, int $akunKasId, User $actor, string $requestKey, string $requestHash): Transaksi
    {
        return DB::transaction(function () use ($pinjaman, $tanggal, $akunKasId, $actor, $requestKey, $requestHash) {
            $p = Pinjaman::whereKey($pinjaman->id)->lockForUpdate()->firstOrFail();

            if ($p->status !== PinjamanStatus::Draft) {
                throw DomainException::invalidState('Hanya pinjaman draft yang dapat dicairkan.');
            }

            $periode = PeriodeAkuntansi::whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)->first();
            if (! $periode) {
                throw DomainException::invalidState('Tidak ada periode akuntansi untuk tanggal pencairan.');
            }

            $trx = Transaksi::create([
                'nomor_transaksi' => 'PC-'.strtoupper((string) Str::ulid()),
                'tanggal' => $tanggal,
                'periode_id' => $periode->id,
                'unit_usaha_id' => $p->unit_usaha_id,
                'pihak_id' => $p->peminjam_id,
                'user_id' => $actor->id,
                'jenis_transaksi' => TransaksiJenis::Pencairan->value,
                'status' => TransaksiStatus::Draft->value,
                'akun_kas_id' => $akunKasId,
                'total' => $p->pokok_pinjaman,
                'request_key' => (string) Str::uuid(),
                'version' => 1,
                'keterangan' => 'Pencairan '.$p->nomor_pinjaman,
            ]);

            $p->update(['transaksi_id' => $trx->id]);

            return $this->posting->post($trx, 1, $actor, $requestKey, $requestHash);
        });
    }
}
