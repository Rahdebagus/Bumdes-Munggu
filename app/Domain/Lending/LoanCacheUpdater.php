<?php

namespace App\Domain\Lending;

use App\Domain\Shared\Decimal;
use App\Enums\AngsuranStatus;
use App\Enums\PinjamanStatus;
use App\Models\Pinjaman;

/**
 * Menyegarkan cache angsuran (pokok/bunga/denda dibayar, status) dan
 * pinjaman.sisa_pokok dari histori pembayaran (LoanBalanceQuery). Dipanggil
 * setelah pembayaran diposting dan setelah pembayaran dibalik.
 */
class LoanCacheUpdater
{
    public function __construct(private LoanBalanceQuery $balance) {}

    public function refresh(Pinjaman $pinjaman): void
    {
        $totalPokokDibayar = '0';
        $semuaLunas = true;

        foreach ($pinjaman->angsuran()->get() as $angsuran) {
            $paid = $this->balance->paid($angsuran);
            $totalPokokDibayar = Decimal::add($totalPokokDibayar, $paid['pokok']);

            $lunas = Decimal::compare($paid['pokok'], (string) $angsuran->pokok_tagihan) >= 0
                && Decimal::compare($paid['bunga'], (string) $angsuran->bunga_tagihan) >= 0
                && Decimal::compare($paid['denda'], (string) $angsuran->denda_tagihan) >= 0;

            $ada = Decimal::isPositive($paid['pokok']) || Decimal::isPositive($paid['bunga']) || Decimal::isPositive($paid['denda']);

            $status = $lunas ? AngsuranStatus::Lunas : ($ada ? AngsuranStatus::Sebagian : AngsuranStatus::BelumDibayar);
            $semuaLunas = $semuaLunas && $lunas;

            $angsuran->update([
                'pokok_dibayar' => $paid['pokok'],
                'bunga_dibayar' => $paid['bunga'],
                'denda_dibayar' => $paid['denda'],
                'tanggal_bayar' => $ada ? ($angsuran->tanggal_bayar ?? now()->toDateString()) : null,
                'status' => $status->value,
            ]);
        }

        $pinjaman->update([
            'sisa_pokok' => Decimal::sub((string) $pinjaman->pokok_pinjaman, $totalPokokDibayar),
            'status' => $semuaLunas ? PinjamanStatus::Lunas->value : PinjamanStatus::Aktif->value,
        ]);
    }
}
