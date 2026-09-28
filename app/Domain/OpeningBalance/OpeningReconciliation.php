<?php

namespace App\Domain\OpeningBalance;

use App\Models\PeriodeAkuntansi;
use App\Models\SaldoAwalAkun;

/**
 * Rekonsiliasi saldo awal: (1) total debit == total kredit; (2) untuk akun
 * kontrol (persediaan/piutang/utang/pinjaman/aset/akumulasi), jumlah rincian
 * harus sama dengan saldo akun pada sisi normalnya (dokumen08 §8).
 */
class OpeningReconciliation
{
    private const CONTROL_KEYS = [
        'inventory', 'trade_receivable', 'loan_receivable',
        'trade_payable', 'fixed_asset', 'accumulated_depreciation',
    ];

    /**
     * @return array{balanced: bool, total_debit: string, total_kredit: string, control_issues: array<int, array<string, string>>, ok: bool}
     */
    public function check(PeriodeAkuntansi $periode): array
    {
        $rows = SaldoAwalAkun::with(['akun', 'rincian'])
            ->where('periode_id', $periode->id)
            ->get();

        $totalDebit = '0';
        $totalKredit = '0';
        foreach ($rows as $r) {
            $totalDebit = bcadd($totalDebit, (string) $r->saldo_debit, 2);
            $totalKredit = bcadd($totalKredit, (string) $r->saldo_kredit, 2);
        }
        $balanced = bccomp($totalDebit, $totalKredit, 2) === 0;

        $issues = [];
        foreach ($rows as $r) {
            $key = $r->akun?->system_key;
            if (! in_array($key, self::CONTROL_KEYS, true)) {
                continue;
            }

            $kontrol = $r->akun->saldo_normal->value === 'debit'
                ? (string) $r->saldo_debit
                : (string) $r->saldo_kredit;

            $rincianSum = '0';
            foreach ($r->rincian as $rc) {
                $rincianSum = bcadd($rincianSum, (string) $rc->nominal, 2);
            }

            if (bccomp($kontrol, $rincianSum, 2) !== 0) {
                $issues[] = [
                    'akun' => $r->akun->kode_akun,
                    'system_key' => (string) $key,
                    'kontrol' => $kontrol,
                    'rincian' => $rincianSum,
                ];
            }
        }

        return [
            'balanced' => $balanced,
            'total_debit' => $totalDebit,
            'total_kredit' => $totalKredit,
            'control_issues' => $issues,
            'ok' => $balanced && $issues === [],
        ];
    }
}
