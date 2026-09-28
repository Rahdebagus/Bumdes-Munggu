<?php

namespace App\Domain\Trading;

use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\TransaksiBarang;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat draft penjualan/pembelian + baris barang (dokumen09 §Kontrak DTO).
 * Draft belum ada efek jurnal/stok; HPP & jurnal dihitung server saat post.
 * Dipakai oleh SaveSaleDraft & SavePurchaseDraft (jenis diberikan pemanggil).
 */
class SaveTradingDraft
{
    /**
     * @param  array{tanggal: string, unit_usaha_id: int, pihak_id: ?int, metode_pembayaran: string, akun_kas_id?: ?int, jatuh_tempo?: ?string, keterangan?: ?string, items: array<int, array{produk_id: int, kuantitas: string, harga: string, diskon?: string, pajak?: string}>}  $data
     */
    public function handle(array $data, TransaksiJenis $jenis, User $actor): Transaksi
    {
        $periode = PeriodeAkuntansi::whereDate('tanggal_mulai', '<=', $data['tanggal'])
            ->whereDate('tanggal_selesai', '>=', $data['tanggal'])
            ->first();

        if (! $periode) {
            throw DomainException::invalidState('Tidak ada periode akuntansi untuk tanggal tersebut.');
        }

        $prefix = $jenis === TransaksiJenis::Penjualan ? 'SL' : 'PB';

        return DB::transaction(function () use ($data, $jenis, $actor, $periode, $prefix) {
            $total = '0';
            foreach ($data['items'] as $item) {
                $net = Decimal::sub(Decimal::mul($item['kuantitas'], $item['harga']), $item['diskon'] ?? 0);
                $total = Decimal::add($total, Decimal::add($net, $item['pajak'] ?? 0));
            }

            $trx = Transaksi::create([
                'nomor_transaksi' => $prefix.'-'.strtoupper((string) Str::ulid()),
                'tanggal' => $data['tanggal'],
                'periode_id' => $periode->id,
                'unit_usaha_id' => $data['unit_usaha_id'],
                'pihak_id' => $data['pihak_id'] ?? null,
                'user_id' => $actor->id,
                'jenis_transaksi' => $jenis->value,
                'status' => TransaksiStatus::Draft->value,
                'metode_pembayaran' => $data['metode_pembayaran'],
                'akun_kas_id' => $data['akun_kas_id'] ?? null,
                'jatuh_tempo' => $data['jatuh_tempo'] ?? null,
                'total' => $total,
                'keterangan' => $data['keterangan'] ?? null,
                'request_key' => (string) Str::uuid(),
                'version' => 1,
            ]);

            $nomor = 1;
            foreach ($data['items'] as $item) {
                $net = Decimal::sub(Decimal::mul($item['kuantitas'], $item['harga']), $item['diskon'] ?? 0);
                TransaksiBarang::create([
                    'transaksi_id' => $trx->id,
                    'produk_id' => $item['produk_id'],
                    'kuantitas' => $item['kuantitas'],
                    'harga' => $item['harga'],
                    'diskon' => $item['diskon'] ?? 0,
                    'pajak' => $item['pajak'] ?? 0,
                    'subtotal' => Decimal::add($net, $item['pajak'] ?? 0),
                    'nomor_baris' => $nomor++,
                ]);
            }

            return $trx;
        });
    }
}
