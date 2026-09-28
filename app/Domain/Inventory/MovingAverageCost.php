<?php

namespace App\Domain\Inventory;

use App\Domain\Shared\Decimal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Perpetual moving weighted average (dokumen08 §4). Semua nilai string decimal.
 */
class MovingAverageCost
{
    /** Harga rata-rata per unit (skala HPP 6) dari nilai & kuantitas saldo. */
    public function average(string $nilai, string $qty): string
    {
        if (Decimal::isZero($qty, Decimal::QTY)) {
            return Decimal::of(0, Decimal::COST);
        }

        return Decimal::of(
            BigDecimal::of($nilai)->dividedBy($qty, Decimal::COST, RoundingMode::HALF_UP),
            Decimal::COST,
        );
    }

    /**
     * Nilai keluar (HPP) untuk penjualan. Jika menghabiskan stok, ambil seluruh
     * sisa nilai agar tidak meninggalkan nilai tanpa kuantitas (dokumen08 §4).
     */
    public function nilaiKeluar(string $qtyOut, string $qtyBefore, string $nilaiBefore): string
    {
        if (Decimal::compare($qtyOut, $qtyBefore, Decimal::QTY) >= 0) {
            return Decimal::of($nilaiBefore, Decimal::MONEY); // ambil semua sisa
        }

        $avg = $this->average($nilaiBefore, $qtyBefore);

        return Decimal::mul($qtyOut, $avg, Decimal::MONEY);
    }
}
