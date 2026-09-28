<?php

namespace App\Domain\OpeningBalance;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Membaca file xlsx/csv saldo awal menjadi baris asosiatif mentah.
 * Kolom header yang dikenali: kode_akun, unit_kode, saldo_debit, saldo_kredit, keterangan.
 * Tidak memvalidasi bisnis — itu tugas PreviewImport.
 */
class OpeningImportParser
{
    private const KOLOM = ['kode_akun', 'unit_kode', 'saldo_debit', 'saldo_kredit', 'keterangan'];

    /** @return array<int, array<string, string>> */
    public function parse(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $matrix = $sheet->toArray(null, true, false, false);

        if ($matrix === []) {
            return [];
        }

        $header = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            array_shift($matrix)
        );
        $map = [];
        foreach (self::KOLOM as $kolom) {
            $idx = array_search($kolom, $header, true);
            if ($idx !== false) {
                $map[$kolom] = $idx;
            }
        }

        $rows = [];
        foreach ($matrix as $baris) {
            // Lewati baris kosong sepenuhnya.
            if (implode('', array_map(fn ($c) => trim((string) $c), $baris)) === '') {
                continue;
            }

            $row = [];
            foreach (self::KOLOM as $kolom) {
                $row[$kolom] = isset($map[$kolom]) ? trim((string) ($baris[$map[$kolom]] ?? '')) : '';
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
