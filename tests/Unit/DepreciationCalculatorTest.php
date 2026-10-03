<?php

namespace Tests\Unit;

use App\Domain\FixedAssets\DepreciationCalculator;
use PHPUnit\Framework\TestCase;

class DepreciationCalculatorTest extends TestCase
{
    private DepreciationCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new DepreciationCalculator;
    }

    public function test_monthly_straight_line(): void
    {
        // Fixture E: 1.000.000, residu 100.000, umur 7 → basis 900.000 / 7 = 128.571,43.
        $this->assertSame('128571.43', $this->calc->monthlyAmount('1000000', '100000', 7, '0'));
    }

    public function test_last_period_takes_remainder(): void
    {
        // Setelah 6 bulan: akumulasi 771.428,58 → sisa 128.571,42 (< standar).
        $this->assertSame('128571.42', $this->calc->monthlyAmount('1000000', '100000', 7, '771428.58'));
    }

    public function test_capped_at_residu(): void
    {
        // Basis habis → 0 (nilai buku tidak turun di bawah residu).
        $this->assertSame('0.00', $this->calc->monthlyAmount('1000000', '100000', 7, '900000'));
    }

    public function test_total_equals_basis_over_life(): void
    {
        $akumulasi = '0';
        $total = '0';
        for ($i = 0; $i < 7; $i++) {
            $amt = $this->calc->monthlyAmount('1000000', '100000', 7, $akumulasi);
            $akumulasi = bcadd($akumulasi, $amt, 2);
            $total = bcadd($total, $amt, 2);
        }
        $this->assertSame('900000.00', $total); // persis basis, tidak melewati residu
    }
}
