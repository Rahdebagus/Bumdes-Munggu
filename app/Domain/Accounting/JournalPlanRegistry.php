<?php

namespace App\Domain\Accounting;

use App\Domain\Accounting\Contracts\JournalPlanner;
use App\Domain\Shared\DomainException;
use App\Models\Transaksi;

/**
 * Peta jenis_transaksi → JournalPlanner. Diisi di service provider; modul
 * mendaftarkan strateginya. Posting menolak jenis tanpa planner.
 */
class JournalPlanRegistry
{
    /** @var array<string, JournalPlanner> */
    private array $planners = [];

    public function register(string $jenisTransaksi, JournalPlanner $planner): void
    {
        $this->planners[$jenisTransaksi] = $planner;
    }

    public function for(Transaksi $trx): JournalPlanner
    {
        $jenis = $trx->jenis_transaksi->value;

        if (! isset($this->planners[$jenis])) {
            throw DomainException::invalidState("Belum ada planner jurnal untuk jenis '{$jenis}'.");
        }

        return $this->planners[$jenis];
    }
}
