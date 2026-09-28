<?php

namespace App\Domain\Accounting;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Shared\DomainException;
use App\Models\Transaksi;

/**
 * Peta jenis_transaksi → PostingStrategy. Diisi di service provider; modul
 * mendaftarkan strateginya. Posting menolak jenis tanpa strategy.
 */
class JournalPlanRegistry
{
    /** @var array<string, PostingStrategy> */
    private array $planners = [];

    public function register(string $jenisTransaksi, PostingStrategy $planner): void
    {
        $this->planners[$jenisTransaksi] = $planner;
    }

    public function for(Transaksi $trx): PostingStrategy
    {
        $jenis = $trx->jenis_transaksi->value;

        if (! isset($this->planners[$jenis])) {
            throw DomainException::invalidState("Belum ada planner jurnal untuk jenis '{$jenis}'.");
        }

        return $this->planners[$jenis];
    }
}
