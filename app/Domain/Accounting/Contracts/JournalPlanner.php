<?php

namespace App\Domain\Accounting\Contracts;

use App\Domain\Accounting\JournalPlan;
use App\Models\Transaksi;

/**
 * Strategy per jenis transaksi yang menghitung rencana jurnal dari data server.
 * Modul dagang/kas/pinjaman/dll. mendaftarkan planner masing-masing (tahap05+).
 */
interface JournalPlanner
{
    public function plan(Transaksi $trx): JournalPlan;
}
