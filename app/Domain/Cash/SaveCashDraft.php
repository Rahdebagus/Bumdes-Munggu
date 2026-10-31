<?php

namespace App\Domain\Cash;

use App\Domain\Shared\Decimal;
use App\Domain\Shared\DocumentNumber;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use App\Models\AlokasiPelunasan;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Draft transaksi kas (pemasukan/pengeluaran) menurut kategori (config
 * accounting). Untuk pelunasan, menulis baris alokasi_pelunasan (belum
 * dihitung di outstanding sampai transaksi kas diposting). Dengan $existing,
 * draft yang sama diperbarui (alokasi diganti, version++). Setoran pinjaman
 * lama membawa bunga & denda per alokasi; total = Σ pokok + bunga + denda.
 */
class SaveCashDraft
{
    private const PELUNASAN = ['pelunasan_piutang', 'pelunasan_utang', 'setoran_pinjaman_lama'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor, ?Transaksi $existing = null): Transaksi
    {
        $kategori = config("accounting.categories.{$data['kategori_kode']}");
        if (! $kategori) {
            throw DomainException::invalidState("Kategori kas '{$data['kategori_kode']}' tidak dikenal.");
        }
        if (isset($kategori['redirect'])) {
            throw DomainException::invalidState($kategori['redirect']['pesan']);
        }

        $jenis = $kategori['arah'] === 'masuk' ? TransaksiJenis::Pemasukan : TransaksiJenis::Pengeluaran;
        $isPelunasan = in_array($data['kategori_kode'], self::PELUNASAN, true);

        if ($existing && ($existing->status !== TransaksiStatus::Draft
            || ! in_array($existing->jenis_transaksi, [TransaksiJenis::Pemasukan, TransaksiJenis::Pengeluaran], true))) {
            throw DomainException::invalidState('Hanya draft kas yang dapat diedit; dokumen terposting dikoreksi lewat Batal Posting.');
        }

        $periode = PeriodeAkuntansi::whereDate('tanggal_mulai', '<=', $data['tanggal'])
            ->whereDate('tanggal_selesai', '>=', $data['tanggal'])
            ->first();
        if (! $periode) {
            throw DomainException::invalidState('Tidak ada periode akuntansi untuk tanggal tersebut.');
        }

        return DB::transaction(function () use ($data, $actor, $periode, $jenis, $isPelunasan, $existing) {
            $total = $isPelunasan
                ? array_reduce($data['allocations'], fn ($s, $a) => Decimal::add(
                    Decimal::add($s, $a['nominal']),
                    Decimal::add($a['bunga'] ?? '0', $a['denda'] ?? '0'),
                ), '0')
                : Decimal::of($data['nominal']);

            $header = [
                'tanggal' => $data['tanggal'],
                'periode_id' => $periode->id,
                'unit_usaha_id' => $data['unit_usaha_id'] ?? null,
                'pihak_id' => $data['pihak_id'] ?? null,
                'jenis_transaksi' => $jenis->value,
                'kategori_kode' => $data['kategori_kode'],
                'akun_kas_id' => $data['akun_kas_id'],
                'akun_lawan_id' => $isPelunasan ? null : ($data['akun_lawan_id'] ?? null),
                'total' => $total,
                'keterangan' => $data['keterangan'] ?? null,
            ];

            if ($existing) {
                $trx = Transaksi::whereKey($existing->id)->lockForUpdate()->firstOrFail();
                $trx->update($header + ['version' => $trx->version + 1]);
                AlokasiPelunasan::where('pembayaran_transaksi_id', $trx->id)->delete();
            } else {
                $trx = Transaksi::create($header + [
                    'nomor_transaksi' => DocumentNumber::next('KS', $data['tanggal']),
                    'user_id' => $actor->id,
                    'request_key' => (string) Str::uuid(),
                    'version' => 1,
                ]);
            }

            if ($isPelunasan) {
                foreach ($data['allocations'] as $a) {
                    AlokasiPelunasan::create([
                        'pembayaran_transaksi_id' => $trx->id,
                        'invoice_transaksi_id' => $a['invoice_transaksi_id'] ?? null,
                        'opening_rincian_id' => $a['opening_rincian_id'] ?? null,
                        'nominal' => $a['nominal'],
                        'bunga' => $a['bunga'] ?? '0',
                        'denda' => $a['denda'] ?? '0',
                    ]);
                }
            }

            return $trx;
        });
    }
}
