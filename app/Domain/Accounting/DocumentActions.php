<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\DocumentNumber;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\AlokasiPelunasan;
use App\Models\BuktiTransaksi;
use App\Models\Transaksi;
use App\Models\TransaksiBarang;
use App\Models\TransaksiDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Aksi dokumen dari Daftar Transaksi: hapus draft dan "Batal Posting".
 *
 * Buku besar immutable (dokumen06): dokumen terposting TIDAK dihapus/diubah.
 * Batal Posting = jurnal pembalik (ReverseTransaction) + salinan dokumen
 * sebagai DRAFT baru yang bisa diedit lalu diposting ulang. Jejak audit
 * lengkap tetap ada (dokumen asli berstatus reversed).
 */
class DocumentActions
{
    /** Jenis dokumen yang dibuat dari form (bisa diedit/dihapus saat draft). */
    public const EDITABLE = [TransaksiJenis::Penjualan, TransaksiJenis::Pembelian, TransaksiJenis::Pemasukan, TransaksiJenis::Pengeluaran];

    public function __construct(private ReverseTransaction $reverser, private AuditWriter $audit) {}

    public static function isFormDocument(Transaksi $trx): bool
    {
        return in_array($trx->jenis_transaksi, self::EDITABLE, true) && $trx->sumber_tipe === null && $trx->reversal_of_id === null;
    }

    /** URL form edit untuk draft dokumen form. */
    public static function editUrl(Transaksi $trx): ?string
    {
        if ($trx->status !== TransaksiStatus::Draft || ! self::isFormDocument($trx)) {
            return null;
        }

        return match ($trx->jenis_transaksi) {
            TransaksiJenis::Penjualan => route('sales.create', ['draft' => $trx->id]),
            TransaksiJenis::Pembelian => route('purchases.create', ['draft' => $trx->id]),
            default => route('cash.create', ['draft' => $trx->id]),
        };
    }

    public function deleteDraft(Transaksi $transaksi, User $actor): void
    {
        DB::transaction(function () use ($transaksi, $actor) {
            $trx = Transaksi::whereKey($transaksi->id)->lockForUpdate()->firstOrFail();
            if ($trx->status !== TransaksiStatus::Draft || ! self::isFormDocument($trx)) {
                throw DomainException::invalidState('Hanya draft penjualan/pembelian/kas yang dapat dihapus. Dokumen terposting dibatalkan lewat Batal Posting.');
            }

            $files = BuktiTransaksi::where('transaksi_id', $trx->id)->pluck('file_bukti')->all();
            BuktiTransaksi::where('transaksi_id', $trx->id)->delete();
            AlokasiPelunasan::where('pembayaran_transaksi_id', $trx->id)->delete();
            TransaksiBarang::where('transaksi_id', $trx->id)->delete();
            TransaksiDetail::where('transaksi_id', $trx->id)->delete();

            $snapshot = ['nomor' => $trx->nomor_transaksi, 'jenis' => $trx->jenis_transaksi->value, 'total' => (string) $trx->total, 'tanggal' => $trx->tanggal->toDateString()];
            $trx->delete();

            $this->audit->log($actor->id, 'Accounting', 'delete_draft', 'transaksi', $transaksi->id, $snapshot, null, 'Hapus draft');

            DB::afterCommit(fn () => Storage::disk('local')->delete(array_filter($files)));
        });
    }

    /**
     * Batalkan posting dokumen form: buat jurnal pembalik pada tanggal dokumen
     * lalu salin isi dokumen menjadi draft baru. Mengembalikan draft baru.
     */
    public function cancelPosting(Transaksi $transaksi, User $actor, string $alasan): Transaksi
    {
        if (! self::isFormDocument($transaksi) || $transaksi->status !== TransaksiStatus::Posted) {
            throw DomainException::invalidState('Batal Posting hanya untuk penjualan/pembelian/kas yang sudah diposting.');
        }

        return DB::transaction(function () use ($transaksi, $actor, $alasan) {
            $key = 'reverse:'.$transaksi->id;
            $this->reverser->reverse($transaksi, $transaksi->tanggal->toDateString(), $alasan, $actor, $key, hash('sha256', $key.$alasan));

            $orig = $transaksi->fresh(['barang']);
            $prefix = match ($orig->jenis_transaksi) {
                TransaksiJenis::Penjualan => 'SL',
                TransaksiJenis::Pembelian => 'PB',
                default => 'KS',
            };

            $draft = Transaksi::create([
                'nomor_transaksi' => DocumentNumber::next($prefix, $orig->tanggal->toDateString()),
                'tanggal' => $orig->tanggal->toDateString(),
                'periode_id' => $orig->periode_id,
                'unit_usaha_id' => $orig->unit_usaha_id,
                'pihak_id' => $orig->pihak_id,
                'user_id' => $actor->id,
                'jenis_transaksi' => $orig->jenis_transaksi->value,
                'status' => TransaksiStatus::Draft->value,
                'metode_pembayaran' => $orig->metode_pembayaran,
                'akun_kas_id' => $orig->akun_kas_id,
                'akun_lawan_id' => $orig->akun_lawan_id,
                'kategori_kode' => $orig->kategori_kode,
                'jatuh_tempo' => $orig->jatuh_tempo?->toDateString(),
                'total' => $orig->total,
                'keterangan' => trim(($orig->keterangan ? $orig->keterangan.' ' : '').'(koreksi '.$orig->nomor_transaksi.')'),
                'request_key' => (string) Str::uuid(),
                'version' => 1,
            ]);

            foreach ($orig->barang->sortBy('nomor_baris') as $b) {
                TransaksiBarang::create([
                    'transaksi_id' => $draft->id,
                    'produk_id' => $b->produk_id,
                    'kuantitas' => $b->kuantitas,
                    'harga' => $b->harga,
                    'diskon' => $b->diskon,
                    'pajak' => $b->pajak,
                    'subtotal' => $b->subtotal,
                    'nomor_baris' => $b->nomor_baris,
                ]);
            }

            foreach (AlokasiPelunasan::where('pembayaran_transaksi_id', $orig->id)->get() as $a) {
                AlokasiPelunasan::create([
                    'pembayaran_transaksi_id' => $draft->id,
                    'invoice_transaksi_id' => $a->invoice_transaksi_id,
                    'opening_rincian_id' => $a->opening_rincian_id,
                    'nominal' => $a->nominal,
                    'bunga' => $a->bunga,
                    'denda' => $a->denda,
                ]);
            }

            $this->audit->log($actor->id, 'Accounting', 'cancel_posting', 'transaksi', $orig->id, null, ['draft_id' => $draft->id], $alasan);

            return $draft;
        });
    }
}
