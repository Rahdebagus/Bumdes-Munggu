<?php

namespace App\Domain\FixedAssets;

use App\Domain\Accounting\PostingService;
use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\AsetTetap;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Posting penyusutan satu aset untuk satu periode. posting_key
 * DEP:<aset>:<periode> unik mencegah duplikat efektif; setelah reverse key
 * dikosongkan sehingga repost terkoreksi dapat memakai key yang sama (dokumen05).
 */
class PostDepreciation
{
    public function __construct(
        private DepreciationCalculator $calculator,
        private PostingService $posting,
    ) {}

    public function handle(AsetTetap $aset, PeriodeAkuntansi $periode, User $actor, string $requestKey, string $requestHash): Transaksi
    {
        return DB::transaction(function () use ($aset, $periode, $actor, $requestKey, $requestHash) {
            $a = AsetTetap::whereKey($aset->id)->lockForUpdate()->firstOrFail();
            $key = "DEP:{$a->id}:{$periode->id}";

            if (Transaksi::where('posting_key', $key)->exists()) {
                throw DomainException::depreciationExists("Penyusutan aset {$a->kode_aset} untuk periode ini sudah ada.");
            }

            $akumulasi = $this->accumulated($a);
            $amount = $this->calculator->monthlyAmount(
                (string) $a->harga_perolehan, (string) $a->nilai_residu, $a->umur_manfaat, $akumulasi
            );

            if (Decimal::compare($amount, 0) <= 0) {
                throw DomainException::invalidState("Aset {$a->kode_aset} sudah tersusut penuh.");
            }

            $trx = Transaksi::create([
                'nomor_transaksi' => 'DP-'.strtoupper((string) Str::ulid()),
                'tanggal' => $periode->tanggal_selesai->toDateString(),
                'periode_id' => $periode->id,
                'unit_usaha_id' => $a->unit_usaha_id,
                'user_id' => $actor->id,
                'jenis_transaksi' => TransaksiJenis::Penyusutan->value,
                'status' => TransaksiStatus::Draft->value,
                'sumber_tipe' => 'aset_tetap',
                'sumber_id' => $a->id,
                'total' => $amount,
                'posting_key' => $key,
                'request_key' => (string) Str::uuid(),
                'version' => 1,
                'keterangan' => 'Penyusutan '.$a->kode_aset.' '.str_pad((string) $periode->bulan, 2, '0', STR_PAD_LEFT).'/'.$periode->tahun,
            ]);

            return $this->posting->post($trx, 1, $actor, $requestKey, $requestHash);
        });
    }

    /** akumulasi_awal + Σ penyusutan terposting (belum dibalik) semua periode. */
    private function accumulated(AsetTetap $a): string
    {
        $posted = Transaksi::where('sumber_tipe', 'aset_tetap')
            ->where('sumber_id', $a->id)
            ->where('jenis_transaksi', TransaksiJenis::Penyusutan->value)
            ->whereNotNull('posted_at')
            ->where('status', 'posted')
            ->sum('total');

        return Decimal::add((string) $a->akumulasi_awal, (string) $posted);
    }
}
