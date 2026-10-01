<?php

namespace App\Domain\Lending;

use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Hitung total bunga & jadwal angsuran bulanan (dokumen08 §5).
 * - flat_total   : total_bunga = pokok × rate%.
 * - flat_bulanan : total_bunga = pokok × rate% × tenor.
 * Pokok/bunga dibagi rata 2 desimal; selisih pembulatan ke jadwal terakhir.
 * Tanggal jatuh tempo mengikuti tanggal jangkar tanpa drift (hari 31 pada bulan
 * pendek memakai hari terakhir, dihitung dari jangkar).
 */
class ScheduleCalculator
{
    public function totalBunga(string $pokok, string $rate, int $tenor, string $basisBunga): string
    {
        $fraction = BigDecimal::of($rate)->dividedBy(100, 10, RoundingMode::HALF_UP);
        $bunga = BigDecimal::of($pokok)->multipliedBy($fraction);

        if ($basisBunga === 'flat_bulanan') {
            $bunga = $bunga->multipliedBy($tenor);
        } elseif ($basisBunga !== 'flat_total') {
            throw DomainException::invalidState("basis_bunga tidak valid: {$basisBunga}.");
        }

        return Decimal::of($bunga, Decimal::MONEY);
    }

    /**
     * @return array<int, array{angsuran_ke: int, tanggal_jatuh_tempo: string, pokok_tagihan: string, bunga_tagihan: string}>
     */
    public function build(string $pokok, string $rate, int $tenor, string $basisBunga, string $tanggalPertama): array
    {
        if ($tenor < 1) {
            throw DomainException::invalidState('Tenor minimal 1 bulan.');
        }

        $totalBunga = $this->totalBunga($pokok, $rate, $tenor, $basisBunga);

        $pokokPer = Decimal::of(BigDecimal::of($pokok)->dividedBy($tenor, Decimal::MONEY, RoundingMode::HALF_UP));
        $bungaPer = Decimal::of(BigDecimal::of($totalBunga)->dividedBy($tenor, Decimal::MONEY, RoundingMode::HALF_UP));

        $pokokSisa = Decimal::sub($pokok, Decimal::mul($pokokPer, $tenor - 1));
        $bungaSisa = Decimal::sub($totalBunga, Decimal::mul($bungaPer, $tenor - 1));

        $anchor = CarbonImmutable::parse($tanggalPertama);
        $jadwal = [];

        for ($i = 1; $i <= $tenor; $i++) {
            $jadwal[] = [
                'angsuran_ke' => $i,
                'tanggal_jatuh_tempo' => $anchor->addMonthsNoOverflow($i - 1)->toDateString(),
                'pokok_tagihan' => $i < $tenor ? $pokokPer : $pokokSisa,
                'bunga_tagihan' => $i < $tenor ? $bungaPer : $bungaSisa,
            ];
        }

        return $jadwal;
    }
}
