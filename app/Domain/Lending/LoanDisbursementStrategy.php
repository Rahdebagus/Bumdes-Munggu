<?php

namespace App\Domain\Lending;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Domain\Accounting\SystemAccounts;
use App\Enums\PinjamanStatus;
use App\Models\Pinjaman;
use App\Models\Transaksi;

/**
 * Pencairan pinjaman (dokumen08 §2): Dr Piutang pinjaman; Cr Kas/bank sebesar
 * pokok. Bunga diakui saat pembayaran, bukan di pencairan.
 */
class LoanDisbursementStrategy implements PostingStrategy
{
    public function plan(Transaksi $trx): JournalPlan
    {
        $sys = new SystemAccounts;
        $kas = $trx->akun_kas_id ?? $sys->id('cash');

        return new JournalPlan(
            new JournalLine($sys->id('loan_receivable'), debit: (string) $trx->total, keterangan: 'Pencairan pinjaman'),
            new JournalLine($kas, kredit: (string) $trx->total, keterangan: 'Kas keluar pencairan', kelompokArusKas: 'operasi'),
        );
    }

    public function documentTotal(Transaksi $trx): string
    {
        return (string) $trx->total;
    }

    public function applyEffects(Transaksi $trx): void
    {
        Pinjaman::where('transaksi_id', $trx->id)->update(['status' => PinjamanStatus::Aktif->value]);
    }
}
