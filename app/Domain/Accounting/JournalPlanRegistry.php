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

    /** @var array<string, PostingStrategy> */
    private array $sourcePlanners = [];

    public function register(string $jenisTransaksi, PostingStrategy $planner): void
    {
        $this->planners[$jenisTransaksi] = $planner;
    }

    /** Daftar strategy berdasarkan sumber_tipe (mis. stok_opname) agar beberapa
     *  sumber dapat memakai jenis 'penyesuaian' yang sama tanpa bentrok. */
    public function registerSource(string $sumberTipe, PostingStrategy $planner): void
    {
        $this->sourcePlanners[$sumberTipe] = $planner;
    }

    public function for(Transaksi $trx): PostingStrategy
    {
        if ($trx->sumber_tipe && isset($this->sourcePlanners[$trx->sumber_tipe])) {
            return $this->sourcePlanners[$trx->sumber_tipe];
        }

        $jenis = $trx->jenis_transaksi->value;

        if (! isset($this->planners[$jenis])) {
            throw DomainException::invalidState("Belum ada planner jurnal untuk jenis '{$jenis}'.");
        }

        return $this->planners[$jenis];
    }
}
