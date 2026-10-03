<?php

namespace App\Domain\FixedAssets;

use App\Domain\Accounting\Contracts\PostingStrategy;
use App\Domain\Accounting\JournalLine;
use App\Domain\Accounting\JournalPlan;
use App\Domain\Accounting\SystemAccounts;
use App\Domain\Shared\DomainException;
use App\Models\AsetTetap;
use App\Models\Transaksi;

/**
 * Posting penyusutan (dokumen08 §2/§7): Dr Beban penyusutan; Cr Akumulasi
 * penyusutan. Memakai akun aset bila ditetapkan, jika tidak memakai system_key
 * default. Nominal = total transaksi yang dihitung PostDepreciation.
 */
class DepreciationStrategy implements PostingStrategy
{
    public function plan(Transaksi $trx): JournalPlan
    {
        $sys = new SystemAccounts;
        $aset = AsetTetap::find($trx->sumber_id);

        if (! $aset) {
            throw DomainException::invalidState('Aset sumber penyusutan tidak ditemukan.');
        }

        $akunBeban = $aset->akun_beban_id ?? $sys->id('depreciation_expense');
        $akunAkumulasi = $aset->akun_akumulasi_id ?? $sys->id('accumulated_depreciation');

        return new JournalPlan(
            new JournalLine($akunBeban, debit: (string) $trx->total, keterangan: 'Beban penyusutan'),
            new JournalLine($akunAkumulasi, kredit: (string) $trx->total, keterangan: 'Akumulasi penyusutan'),
        );
    }

    public function documentTotal(Transaksi $trx): string
    {
        return (string) $trx->total;
    }

    public function applyEffects(Transaksi $trx): void
    {
        // Akumulasi diturunkan dari transaksi penyusutan terposting (query), tidak
        // ada cache yang perlu ditulis di sini.
    }
}
