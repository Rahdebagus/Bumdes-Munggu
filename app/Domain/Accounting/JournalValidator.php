<?php

namespace App\Domain\Accounting;

use App\Domain\Shared\Decimal;
use App\Domain\Shared\DomainException;
use App\Models\Akun;

class JournalValidator
{
    public function validate(JournalPlan $plan): void
    {
        if ($plan->count() < 2) {
            throw DomainException::invalidState('Jurnal minimal dua baris.');
        }

        foreach ($plan->lines as $line) {
            if (! $line->isOneSided()) {
                throw DomainException::invalidState('Tiap baris jurnal harus tepat satu sisi (debit atau kredit) positif.');
            }
        }

        $akunIds = array_values(array_unique(array_map(fn (JournalLine $l) => $l->akunId, $plan->lines)));
        $aktif = Akun::whereIn('id', $akunIds)->where('is_active', true)->pluck('id')->all();
        if (count($aktif) !== count($akunIds)) {
            throw DomainException::invalidState('Ada akun jurnal yang tidak ditemukan atau nonaktif.');
        }

        if (! $plan->isBalanced()) {
            throw DomainException::unbalanced(
                "Jurnal tidak seimbang: debit {$plan->totalDebit()} ≠ kredit {$plan->totalKredit()}."
            );
        }

        if (! Decimal::isPositive($plan->total())) {
            throw DomainException::invalidState('Total jurnal harus lebih dari nol.');
        }
    }
}
