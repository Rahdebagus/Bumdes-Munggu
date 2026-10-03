<?php

namespace App\Domain\FixedAssets;

use App\Domain\Shared\Decimal;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Penyusutan garis lurus bulanan penuh (dokumen08 §7). Nilai bulanan =
 * (perolehan − residu) / umur; periode terakhir disesuaikan ke sisa basis
 * sehingga total persis basis dan nilai buku tidak turun di bawah residu.
 */
class DepreciationCalculator
{
    public function monthlyAmount(string $perolehan, string $residu, int $umur, string $akumulasiSudah): string
    {
        $base = Decimal::sub($perolehan, $residu);
        $standar = Decimal::of(BigDecimal::of($base)->dividedBy($umur, Decimal::MONEY, RoundingMode::HALF_UP));
        $sisa = Decimal::sub($base, $akumulasiSudah);

        if (Decimal::compare($sisa, 0) <= 0) {
            return '0.00';
        }

        // Periode terakhir (sisa < standar) mengambil sisa agar total = basis.
        return Decimal::compare($sisa, $standar) < 0 ? $sisa : $standar;
    }
}
