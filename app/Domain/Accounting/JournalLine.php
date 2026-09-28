<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\Decimal;

/**
 * Satu baris rencana jurnal (nilai uang canonical 2 desimal).
 */
final class JournalLine
{
    public readonly string $debit;

    public readonly string $kredit;

    public function __construct(
        public readonly int $akunId,
        string|int|float $debit = 0,
        string|int|float $kredit = 0,
        public readonly ?string $kelompokArusKas = null,
        public readonly ?string $keterangan = null,
    ) {
        $this->debit = Decimal::of($debit, Decimal::MONEY);
        $this->kredit = Decimal::of($kredit, Decimal::MONEY);
    }

    public function isOneSided(): bool
    {
        $debitPos = Decimal::isPositive($this->debit);
        $kreditPos = Decimal::isPositive($this->kredit);

        return ($debitPos && ! $kreditPos) || ($kreditPos && ! $debitPos);
    }
}
