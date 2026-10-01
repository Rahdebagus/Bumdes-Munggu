<?php

namespace Tests\Unit;

use App\Domain\Lending\ScheduleCalculator;
use PHPUnit\Framework\TestCase;

class ScheduleCalculatorTest extends TestCase
{
    private ScheduleCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new ScheduleCalculator;
    }

    public function test_total_bunga_flat_total(): void
    {
        // Fixture C: pokok 1.000.000, flat_total 10% → 100.000.
        $this->assertSame('100000.00', $this->calc->totalBunga('1000000', '10', 3, 'flat_total'));
        // Fixture A: 1.200.000, flat_total 12% → 144.000.
        $this->assertSame('144000.00', $this->calc->totalBunga('1200000', '12', 12, 'flat_total'));
    }

    public function test_total_bunga_flat_bulanan(): void
    {
        $this->assertSame('300000.00', $this->calc->totalBunga('1000000', '10', 3, 'flat_bulanan'));
    }

    public function test_schedule_splits_with_remainder_on_last(): void
    {
        $jadwal = $this->calc->build('1000000', '10', 3, 'flat_total', '2026-02-01');

        $this->assertSame('333333.33', $jadwal[0]['pokok_tagihan']);
        $this->assertSame('333333.33', $jadwal[1]['pokok_tagihan']);
        $this->assertSame('333333.34', $jadwal[2]['pokok_tagihan']); // sisa pembulatan
        $this->assertSame('33333.33', $jadwal[0]['bunga_tagihan']);
        $this->assertSame('33333.34', $jadwal[2]['bunga_tagihan']);

        $sumPokok = array_sum(array_map(fn ($r) => (float) $r['pokok_tagihan'], $jadwal));
        $this->assertEqualsWithDelta(1000000.0, $sumPokok, 0.001);
    }

    public function test_anchor_date_does_not_drift(): void
    {
        $jadwal = $this->calc->build('900000', '0', 3, 'flat_total', '2026-01-31');

        $this->assertSame('2026-01-31', $jadwal[0]['tanggal_jatuh_tempo']);
        $this->assertSame('2026-02-28', $jadwal[1]['tanggal_jatuh_tempo']); // bulan pendek
        $this->assertSame('2026-03-31', $jadwal[2]['tanggal_jatuh_tempo']); // kembali ke 31, tanpa drift
    }
}
