<?php

namespace App\Http\Controllers;

use App\Models\Akun;
use App\Models\UnitUsaha;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Unduh format impor Saldo Awal, dibangun dari bagan akun & unit usaha yang
 * aktif sekarang (kode selalu sesuai database). XLSX berisi lembar
 * saldo_awal (diisi & diunggah), Petunjuk, Daftar Akun, Daftar Unit. CSV hanya
 * lembar saldo_awal. Contoh baris: Kas + Bank = Modal Desa Rp 50.000.000.
 */
class OpeningBalanceTemplateController extends Controller
{
    /** Akun kontrol: wajib rincian per produk/pelanggan/pemasok/peminjam/aset. */
    private const KONTROL = [
        'inventory' => 'per produk (kode produk)',
        'trade_receivable' => 'per pelanggan (kode pihak)',
        'loan_receivable' => 'per peminjam (kode pihak)',
        'trade_payable' => 'per pemasok (kode pihak)',
        'fixed_asset' => 'per aset (kode aset)',
        'accumulated_depreciation' => 'per aset (kode aset)',
    ];

    public function __invoke(string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $unit = UnitUsaha::where('kode_unit', 'UNIT-DAGANG')->value('kode_unit')
            ?? UnitUsaha::orderBy('id')->value('kode_unit') ?? 'UNIT-DAGANG';
        $kode = fn (string $key, string $fallback) => Akun::where('system_key', $key)->value('kode_akun') ?? $fallback;

        $header = ['kode_akun', 'unit_kode', 'saldo_debit', 'saldo_kredit', 'keterangan'];
        $contoh = [
            [$kode('cash', '1.1.01.01'), $unit, '10000000', '0', 'Uang tunai di brankas per tanggal cutover'],
            [$kode('bank', '1.1.01.02'), $unit, '40000000', '0', 'Saldo rekening koran bank per tanggal cutover'],
            [$kode('capital', '3.1.01.00'), $unit, '0', '50000000', 'Modal penyertaan Desa Munggu'],
        ];

        if ($format === 'csv') {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, $header);
            foreach ($contoh as $row) {
                fputcsv($out, $row);
            }
            rewind($out);
            $csv = stream_get_contents($out);
            fclose($out);

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="format-saldo-awal.csv"',
            ]);
        }

        $book = new Spreadsheet;

        // 1. saldo_awal — lembar yang diisi lalu diunggah.
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('saldo_awal');
        $this->writeRows($sheet, array_merge([$header], $contoh));
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        // Kode akun HARUS teks agar "4.4.1" / "8.0" tidak diubah Excel jadi angka/tanggal.
        $sheet->getStyle('A:B')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('C:D')->getNumberFormat()->setFormatCode('#,##0');
        foreach (['A' => 14, 'B' => 16, 'C' => 16, 'D' => 16, 'E' => 50] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // 2. Petunjuk.
        $p = $book->createSheet();
        $p->setTitle('Petunjuk');
        $this->writeRows($p, array_map(fn ($l) => [$l], $this->petunjuk()));
        $p->getColumnDimension('A')->setWidth(120);
        $p->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        // 3. Daftar Akun (aktif).
        $a = $book->createSheet();
        $a->setTitle('Daftar Akun');
        $rows = [['kode_akun', 'nama_akun', 'jenis', 'saldo normal (isi di kolom)', 'perlu rincian?']];
        foreach (Akun::where('is_active', true)->orderBy('kode_akun')->get() as $akun) {
            $rows[] = [
                $akun->kode_akun,
                $akun->nama_akun,
                $akun->jenis_akun->value,
                $akun->saldo_normal->value === 'debit' ? 'saldo_debit' : 'saldo_kredit',
                isset(self::KONTROL[$akun->system_key]) ? 'YA — '.self::KONTROL[$akun->system_key] : '',
            ];
        }
        $this->writeRows($a, $rows);
        $a->getStyle('A1:E1')->getFont()->setBold(true);
        foreach (['A' => 14, 'B' => 36, 'C' => 12, 'D' => 26, 'E' => 40] as $col => $w) {
            $a->getColumnDimension($col)->setWidth($w);
        }

        // 4. Daftar Unit.
        $u = $book->createSheet();
        $u->setTitle('Daftar Unit');
        $this->writeRows($u, array_merge([['unit_kode', 'nama_unit']], UnitUsaha::orderBy('kode_unit')->get(['kode_unit', 'nama_unit'])->map(fn ($x) => [$x->kode_unit, $x->nama_unit])->all()));
        $u->getStyle('A1:B1')->getFont()->setBold(true);
        $u->getColumnDimension('A')->setWidth(18);
        $u->getColumnDimension('B')->setWidth(30);

        $book->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, 'format-saldo-awal.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return array<int, string> */
    public static function petunjuk(): array
    {
        return [
            'PETUNJUK PENGISIAN SALDO AWAL — SIA BUMDes Munggu',
            '',
            'Saldo awal = posisi neraca (harta, utang, modal) pada tanggal cutover, yaitu hari terakhir sebelum mulai mencatat di aplikasi.',
            'Ambil angkanya dari Neraca / Laporan Posisi Keuangan Excel terakhir (mis. per 31 Desember atau akhir bulan sebelum go-live).',
            '',
            '1. Isi lembar "saldo_awal" — satu baris per akun per unit usaha:',
            '   kode_akun    : lihat lembar "Daftar Akun" (kolom ini harus berformat TEKS, jangan diubah jadi angka).',
            '   unit_kode    : lihat lembar "Daftar Unit" (mis. UNIT-DAGANG). Bila saldo tidak dipisah per unit, pakai satu unit saja.',
            '   saldo_debit  : diisi untuk akun bersaldo normal DEBIT (Kas, Bank, Piutang, Persediaan, Inventaris).',
            '   saldo_kredit : diisi untuk akun bersaldo normal KREDIT (Utang, Modal, Dana-dana, Akm. Penyusutan).',
            '   keterangan   : bebas (sumber angka).',
            '   Tulis angka tanpa titik/Rp, mis. 50000000. Isi 0 pada kolom yang tidak dipakai.',
            '2. Total saldo_debit HARUS sama dengan total saldo_kredit (contoh: Kas 10 jt + Bank 40 jt = Modal 50 jt).',
            '3. Akun pendapatan (4.x), HPP (5.x) dan biaya (6.x, 8.0) TIDAK diisi — saldo awal hanya akun neraca.',
            '4. Akun bertanda "perlu rincian" (Persediaan, Piutang, Utang, Pinjaman, Inventaris) wajib dirinci setelah impor di menu Saldo Awal',
            '   → "Rincian Akun Kontrol"; jumlah rincian harus sama dengan saldonya. Cara paling mudah untuk mulai:',
            '   cukup isi Kas, Bank, Modal & Dana dulu. Stok barang dicatat lewat Data Produk / perintah',
            '   php artisan produk:import produk.xlsx --stok-awal=YYYY-MM-DD (otomatis D Persediaan — K Modal).',
            '   Jangan isi Persediaan di sini DAN stok awal produk sekaligus (tercatat dua kali).',
            '5. Simpan, lalu di menu Saldo Awal: pilih periode cutover → Telusuri file → Preview → periksa → Simpan.',
            '6. Pastikan badge "Debit = Kredit" dan "Rincian kontrol cocok" hijau, lalu klik "Kunci Saldo Awal".',
            '   Setelah dikunci saldo tidak bisa diubah dan transaksi mulai bisa diposting.',
        ];
    }

    /** @param array<int, array<int, string>> $rows */
    private function writeRows($sheet, array $rows): void
    {
        foreach ($rows as $r => $cells) {
            foreach (array_values($cells) as $c => $value) {
                $isNumber = is_numeric($value) && $c >= 2 && $sheet->getTitle() === 'saldo_awal' && $r > 0;
                $sheet->setCellValueExplicit([$c + 1, $r + 1], $isNumber ? (float) $value : (string) $value,
                    $isNumber ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
        }
    }
}
