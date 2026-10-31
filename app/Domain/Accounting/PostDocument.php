<?php

namespace App\Domain\Accounting;

use App\Models\AlokasiPelunasan;
use App\Models\Transaksi;
use App\Models\User;

/**
 * Posting satu dokumen draft dari UI (detail transaksi, "Simpan & Posting" di
 * form). Membangun request key + hash idempotency dari isi dokumen server
 * sehingga klik ganda tidak membuat jurnal ganda, lalu memanggil PostingService.
 */
class PostDocument
{
    public function __construct(private PostingService $service) {}

    public function handle(Transaksi $transaksi, User $actor): Transaksi
    {
        $trx = Transaksi::with(['details', 'barang'])->findOrFail($transaksi->id);

        $payload = [
            'id' => $trx->id,
            'version' => $trx->version,
            'barang' => $trx->barang->map(fn ($b) => [$b->produk_id, (string) $b->kuantitas, (string) $b->harga, (string) $b->diskon, (string) $b->pajak])->all(),
            'details' => $trx->details->map(fn ($d) => [$d->akun_id, (string) $d->debit, (string) $d->kredit])->all(),
            'alokasi' => AlokasiPelunasan::where('pembayaran_transaksi_id', $trx->id)
                ->get()->map(fn ($a) => [$a->target_key, (string) $a->nominal])->all(),
        ];
        $hash = hash('sha256', (string) json_encode($payload));
        $key = 'post:'.$trx->id.':'.$trx->version;

        return $this->service->post($trx, $trx->version, $actor, $key, $hash);
    }
}
