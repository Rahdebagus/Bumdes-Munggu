<?php

namespace App\Domain\Shared;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Helper decimal server untuk uang (2), kuantitas (4), dan HPP unit (6).
 * Pembulatan HALF_UP (dokumen08 §1). Nilai selalu string canonical.
 */
final class Decimal
{
    public const MONEY = 2;

    public const QTY = 4;

    public const COST = 6;

    public static function of(string|int|float $value, int $scale = self::MONEY): string
    {
        return (string) BigDecimal::of($value)->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function add(string|int|float $a, string|int|float $b, int $scale = self::MONEY): string
    {
        return (string) BigDecimal::of($a)->plus($b)->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function sub(string|int|float $a, string|int|float $b, int $scale = self::MONEY): string
    {
        return (string) BigDecimal::of($a)->minus($b)->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function mul(string|int|float $a, string|int|float $b, int $scale = self::MONEY): string
    {
        return (string) BigDecimal::of($a)->multipliedBy($b)->toScale($scale, RoundingMode::HALF_UP);
    }

    /** -1 jika a<b, 0 jika sama, 1 jika a>b (pada skala tertentu). */
    public static function compare(string|int|float $a, string|int|float $b, int $scale = self::MONEY): int
    {
        return BigDecimal::of($a)->toScale($scale, RoundingMode::HALF_UP)
            ->compareTo(BigDecimal::of($b)->toScale($scale, RoundingMode::HALF_UP));
    }

    public static function isPositive(string|int|float $a, int $scale = self::MONEY): bool
    {
        return self::compare($a, 0, $scale) > 0;
    }

    public static function isZero(string|int|float $a, int $scale = self::MONEY): bool
    {
        return self::compare($a, 0, $scale) === 0;
    }
}
