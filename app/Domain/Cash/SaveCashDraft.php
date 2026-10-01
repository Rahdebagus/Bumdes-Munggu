<?php

namespace App\Domain\Cash;

use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Enums\TransaksiJenis;
use App\Models\AlokasiPelunasan;
use App\Models\PeriodeAkuntansi;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Draft transaksi kas (pemasukan/pengeluaran) menurut kategori (config
 * accounting). Untuk pelunasan, menulis baris alokasi_pelunasan (belum
 * dihitung di outstanding sampai transaksi kas diposting).
 */
class SaveCashDraft
{
    private const PELUNASAN = ['pelunasan_piutang', 'pelunasan_utang'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Transaksi
    {
        $kategori = config("accounting.categories.{$data['kategori_kode']}");
        if (! $kategori) {
            throw DomainException::invalidState("Kategori kas '{$data['kategori_kode']}' tidak dikenal.");
        }

        $jenis = $kategori['arah'] === 'masuk' ? TransaksiJenis::Pemasukan : TransaksiJenis::Pengeluaran;
        $isPelunasan = in_array($data['kategori_kode'], self::PELUNASAN, true);

        $periode = PeriodeAkuntansi::whereDate('tanggal_mulai', '<=', $data['tanggal'])
            ->whereDate('tanggal_selesai', '>=', $data['tanggal'])
            ->first();
        if (! $periode) {
            throw DomainException::invalidState('Tidak ada periode akuntansi untuk tanggal tersebut.');
        }

        return DB::transaction(function () use ($data, $actor, $periode, $jenis, $isPelunasan) {
            $total = $isPelunasan
                ? array_reduce($data['allocations'], fn ($s, $a) => Decimal::add($s, $a['nominal']), '0')
                : Decimal::of($data['nominal']);

            $trx = Transaksi::create([
                'nomor_transaksi' => 'KS-'.strtoupper((string) Str::ulid()),
                'tanggal' => $data['tanggal'],
                'periode_id' => $periode->id,
                'unit_usaha_id' => $data['unit_usaha_id'] ?? null,
                'pihak_id' => $data['pihak_id'] ?? null,
                'user_id' => $actor->id,
                'jenis_transaksi' => $jenis->value,
                'kategori_kode' => $data['kategori_kode'],
                'akun_kas_id' => $data['akun_kas_id'],
                'akun_lawan_id' => $isPelunasan ? null : ($data['akun_lawan_id'] ?? null),
                'total' => $total,
                'keterangan' => $data['keterangan'] ?? null,
                'request_key' => (string) Str::uuid(),
                'version' => 1,
            ]);

            if ($isPelunasan) {
                foreach ($data['allocations'] as $a) {
                    AlokasiPelunasan::create([
                        'pembayaran_transaksi_id' => $trx->id,
                        'invoice_transaksi_id' => $a['invoice_transaksi_id'] ?? null,
                        'opening_rincian_id' => $a['opening_rincian_id'] ?? null,
                        'nominal' => $a['nominal'],
                    ]);
                }
            }

            return $trx;
        });
    }
}
