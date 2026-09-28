<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\Decimal;

/**
 * Rencana jurnal: kumpulan JournalLine. Dihitung ulang saat post (dokumen06).
 */
final class JournalPlan
{
    /** @var array<int, JournalLine> */
    public readonly array $lines;

    public function __construct(JournalLine ...$lines)
    {
        $this->lines = array_values($lines);
    }

    public function totalDebit(): string
    {
        return array_reduce($this->lines, fn ($sum, $l) => Decimal::add($sum, $l->debit), '0');
    }

    public function totalKredit(): string
    {
        return array_reduce($this->lines, fn ($sum, $l) => Decimal::add($sum, $l->kredit), '0');
    }

    public function isBalanced(): bool
    {
        return Decimal::compare($this->totalDebit(), $this->totalKredit()) === 0;
    }

    /** Total transaksi = sisi debit (== kredit bila seimbang). */
    public function total(): string
    {
        return $this->totalDebit();
    }

    public function count(): int
    {
        return count($this->lines);
    }
}
