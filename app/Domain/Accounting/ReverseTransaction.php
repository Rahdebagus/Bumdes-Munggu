<?php

namespace App\Domain\Accounting;

use App\Domain\Inventory\StockLedger;
use App\Domain\Lending\LoanCacheUpdater;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\AlokasiPelunasan;
use App\Models\LogAktivitas;
use App\Models\MutasiStok;
use App\Models\PembayaranAngsuran;
use App\Models\PeriodeAkuntansi;
use App\Models\Pinjaman;
use App\Models\Produk;
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
        private StockLedger $stockLedger,
        private LoanCacheUpdater $loanCache,
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

            // Dependensi: invoice kredit yang sudah ada pelunasan terposting
            // harus dibalik pelunasannya lebih dulu (dokumen06 §Reverse).
            $this->assertNoPostedSettlement($orig);
            // Dependensi stok: mutasi transaksi ini harus yang terbaru per produk.
            $this->assertStockMovementsAreLatest($orig);
            // Dependensi pinjaman: pencairan tak bisa dibalik bila sudah ada angsuran.
            $this->assertNoPostedInstallments($orig);

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

            // Balik efek stok (mencerminkan mutasi_stok asli).
            $this->reverseStock($reversal, $orig);

            // Original menjadi reversed; posted_at TIDAK diubah. Alokasi pelunasan
            // yang melekat pada original otomatis berhenti dihitung (status filter).
            // posting_key dikosongkan agar repost terkoreksi (mis. penyusutan) bisa
            // memakai key yang sama (dokumen05).
            $orig->update(['status' => TransaksiStatus::Reversed->value, 'posting_key' => null]);

            // Pembayaran angsuran dibalik → segarkan cache pinjaman dari histori.
            if ($orig->jenis_transaksi === TransaksiJenis::Angsuran) {
                $loan = PembayaranAngsuran::with('angsuran.pinjaman')
                    ->where('transaksi_id', $orig->id)->first()?->angsuran?->pinjaman;
                if ($loan) {
                    $this->loanCache->refresh($loan);
                }
            }

            $periode->increment('revision');

            $this->audit->log($actor->id, 'Accounting', 'reverse', 'transaksi', $orig->id, null, ['reversal_id' => $reversal->id], $reason, $requestKey, $requestHash, ['transaksi_id' => $reversal->id]);

            return $reversal;
        });
    }

    private function assertNoPostedSettlement(Transaksi $orig): void
    {
        $hasSettlement = AlokasiPelunasan::where('invoice_transaksi_id', $orig->id)
            ->whereHas('pembayaranTransaksi', fn ($q) => $q->whereNotNull('posted_at')->where('status', 'posted'))
            ->exists();

        if ($hasSettlement) {
            throw DomainException::reverseDependency('Balik pelunasan yang terkait transaksi ini terlebih dahulu.');
        }
    }

    private function assertNoPostedInstallments(Transaksi $orig): void
    {
        if ($orig->jenis_transaksi !== TransaksiJenis::Pencairan) {
            return;
        }

        $loan = Pinjaman::where('transaksi_id', $orig->id)->first();
        if (! $loan) {
            return;
        }

        $hasPayment = PembayaranAngsuran::whereHas('angsuran', fn ($q) => $q->where('pinjaman_id', $loan->id))
            ->whereHas('transaksi', fn ($q) => $q->whereNotNull('posted_at')->where('status', 'posted'))
            ->exists();

        if ($hasPayment) {
            throw DomainException::reverseDependency('Balik pembayaran angsuran pinjaman ini terlebih dahulu.');
        }
    }

    private function assertStockMovementsAreLatest(Transaksi $orig): void
    {
        $produkIds = MutasiStok::where('transaksi_id', $orig->id)->pluck('produk_id')->unique();

        foreach ($produkIds as $pid) {
            $last = MutasiStok::where('produk_id', $pid)->orderByDesc('urutan')->first();
            if ($last && $last->transaksi_id !== $orig->id) {
                throw DomainException::reverseDependency(
                    'Ada mutasi stok lebih baru pada produk terkait; balik transaksi terbaru lebih dulu.'
                );
            }
        }
    }

    private function reverseStock(Transaksi $reversal, Transaksi $orig): void
    {
        $mutasis = MutasiStok::where('transaksi_id', $orig->id)->orderBy('urutan')->get();
        if ($mutasis->isEmpty()) {
            return;
        }

        $produk = Produk::whereIn('id', $mutasis->pluck('produk_id')->unique())
            ->lockForUpdate()->get()->keyBy('id');

        foreach ($mutasis as $m) {
            $this->stockLedger->reverseMovement($m, $produk[$m->produk_id], $reversal->id, $reversal->tanggal->toDateString());
        }
    }
}
