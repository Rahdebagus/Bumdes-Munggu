<?php

namespace App\Domain\OpeningBalance;

use App\Models\Akun;
use App\Models\UnitUsaha;

/**
 * Memvalidasi baris impor saldo awal per baris (tanpa menulis DB) dan
 * menghasilkan preview: baris valid/invalid + error per baris + total debit,
 * total kredit, dan status seimbang. Commit hanya boleh jika tidak ada error.
 */
class PreviewImport
{
    /**
     * @param  array<int, array<string, string>>  $rawRows
     * @return array{rows: array<int, array<string, mixed>>, total_debit: string, total_kredit: string, balanced: bool, has_errors: bool, row_count: int}
     */
    public function handle(array $rawRows): array
    {
        $akunByKode = Akun::all()->keyBy('kode_akun');
        $unitByKode = UnitUsaha::pluck('id', 'kode_unit');

        $rows = [];
        $totalDebit = '0';
        $totalKredit = '0';
        $hasErrors = false;

        foreach ($rawRows as $i => $raw) {
            $errors = [];
            $kode = $raw['kode_akun'] ?? '';
            $akun = $akunByKode->get($kode);

            if ($kode === '') {
                $errors[] = 'kode_akun wajib diisi';
            } elseif (! $akun) {
                $errors[] = "Akun '{$kode}' tidak ditemukan";
            } elseif (! $akun->is_active) {
                $errors[] = "Akun '{$kode}' nonaktif";
            }

            $unitId = null;
            if (($raw['unit_kode'] ?? '') !== '') {
                $unitId = $unitByKode->get($raw['unit_kode']);
                if (! $unitId) {
                    $errors[] = "Unit '{$raw['unit_kode']}' tidak ditemukan";
                }
            }

            $debit = $this->normalizeAmount($raw['saldo_debit'] ?? '');
            $kredit = $this->normalizeAmount($raw['saldo_kredit'] ?? '');

            if ($debit === null) {
                $errors[] = 'saldo_debit bukan angka valid';
            } elseif (bccomp($debit, '0', 2) < 0) {
                $errors[] = 'saldo_debit tidak boleh negatif';
            }
            if ($kredit === null) {
                $errors[] = 'saldo_kredit bukan angka valid';
            } elseif (bccomp($kredit, '0', 2) < 0) {
                $errors[] = 'saldo_kredit tidak boleh negatif';
            }

            if ($debit !== null && $kredit !== null
                && bccomp($debit, '0', 2) > 0 && bccomp($kredit, '0', 2) > 0) {
                $errors[] = 'Hanya satu sisi (debit atau kredit) yang boleh positif';
            }

            if ($errors === [] && $debit !== null && $kredit !== null) {
                $totalDebit = bcadd($totalDebit, $debit, 2);
                $totalKredit = bcadd($totalKredit, $kredit, 2);
            }

            if ($errors !== []) {
                $hasErrors = true;
            }

            $rows[] = [
                'no' => $i + 1,
                'kode_akun' => $kode,
                'akun_id' => $akun?->id,
                'nama_akun' => $akun?->nama_akun,
                'unit_kode' => $raw['unit_kode'] ?? '',
                'unit_usaha_id' => $unitId,
                'saldo_debit' => $debit ?? '0',
                'saldo_kredit' => $kredit ?? '0',
                'keterangan' => $raw['keterangan'] ?? '',
                'errors' => $errors,
            ];
        }

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_kredit' => $totalKredit,
            'balanced' => bccomp($totalDebit, $totalKredit, 2) === 0,
            'has_errors' => $hasErrors,
            'row_count' => count($rows),
        ];
    }

    private function normalizeAmount(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '0';
        }
        // Buang pemisah ribuan sederhana; harapkan angka desimal titik.
        $clean = str_replace([' ', ','], ['', ''], $value);

        return is_numeric($clean) ? number_format((float) $clean, 2, '.', '') : null;
    }
}
