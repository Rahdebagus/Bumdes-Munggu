<?php

namespace App\Domain\Reporting;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor laporan ke PDF dan XLSX dari dataset ReportService yang sama (dokumen10
 * §PDF dan Excel). String berawalan =,+,-,@ disanitasi agar tidak menjadi
 * formula injeksi; angka numeric; file disimpan privat (disk local).
 */
class ReportExporter
{
    public function __construct(private ReportDataset $datasets) {}

    /**
     * @param  array{from: string, to: string, asOf?: string, unitId?: ?int, cutoverFrom?: string}  $filters
     * @return array{path: string, filename: string}
     */
    public function pdf(string $type, array $filters): array
    {
        $data = $this->datasets->build($type, $filters);
        $html = view('reports.print', ['data' => $data])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        $filename = $this->filename($type, $data, 'pdf');
        $path = 'laporan/'.Str::ulid().'_'.$filename;
        Storage::disk('local')->put($path, $pdf->output());

        return ['path' => $path, 'filename' => $filename];
    }

    /**
     * @param  array{from: string, to: string, asOf?: string, unitId?: ?int, cutoverFrom?: string}  $filters
     * @return array{path: string, filename: string}
     */
    public function xlsx(string $type, array $filters): array
    {
        $data = $this->datasets->build($type, $filters);

        $book = new Spreadsheet;
        $this->sheetRingkasan($book, $data);
        $this->sheetRincian($book, $data);
        $this->sheetMetadata($book, $data);
        $book->setActiveSheetIndex(0);

        $filename = $this->filename($type, $data, 'xlsx');
        $path = 'laporan/'.Str::ulid().'_'.$filename;
        $full = Storage::disk('local')->path($path);
        Storage::disk('local')->makeDirectory('laporan');
        (new Xlsx($book))->save($full);

        return ['path' => $path, 'filename' => $filename];
    }

    private function sheetRingkasan(Spreadsheet $book, array $data): void
    {
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Ringkasan');
        $sheet->setCellValue('A1', $this->s($data['title']));
        $sheet->setCellValue('A2', $this->s('Periode: '.$data['periode'].' — '.$data['unit']));
        $row = 4;
        $sheet->setCellValue("A{$row}", 'Keterangan');
        $sheet->setCellValue("B{$row}", 'Nilai');
        $row++;
        foreach ($data['summary'] as $item) {
            $sheet->setCellValueExplicit("A{$row}", $this->s($item['label']), DataType::TYPE_STRING);
            // Nilai sebagai numeric dengan format uang (nilai resmi mengikuti server).
            $sheet->setCellValueExplicit("B{$row}", (float) $item['value'], DataType::TYPE_NUMERIC);
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }
        $sheet->getColumnDimension('A')->setWidth(45);
        $sheet->getColumnDimension('B')->setWidth(20);
    }

    private function sheetRincian(Spreadsheet $book, array $data): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Rincian');

        if (($data['detail'] ?? null) === null) {
            $sheet->setCellValue('A1', 'Tidak ada rincian untuk laporan ini.');

            return;
        }

        $col = 1;
        foreach ($data['detail']['headers'] as $h) {
            $sheet->setCellValueExplicit([$col, 1], $this->s($h), DataType::TYPE_STRING);
            $col++;
        }
        $r = 2;
        foreach ($data['detail']['rows'] as $cells) {
            $c = 1;
            foreach ($cells as $cell) {
                $this->writeCell($sheet, $c, $r, $cell);
                $c++;
            }
            $r++;
        }
        if (($data['detail']['foot'] ?? null) !== null) {
            $c = 1;
            foreach ($data['detail']['foot'] as $cell) {
                $this->writeCell($sheet, $c, $r, $cell);
                $c++;
            }
        }
    }

    private function sheetMetadata(Spreadsheet $book, array $data): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Metadata');
        $meta = [
            ['Laporan', $data['title']],
            ['Periode', $data['periode']],
            ['Unit', $data['unit']],
            ['Dibuat', $data['generated_at']],
            ['Status', 'DRAFT — bukan laporan resmi bertandatangan'],
            ['Sumber', 'ReportService (jurnal terposting + saldo awal terkunci)'],
        ];
        $r = 1;
        foreach ($meta as [$k, $v]) {
            $sheet->setCellValueExplicit("A{$r}", $this->s($k), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$r}", $this->s($v), DataType::TYPE_STRING);
            $r++;
        }
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(55);
    }

    private function writeCell($sheet, int $col, int $row, array $cell): void
    {
        if (array_key_exists('num', $cell)) {
            $sheet->setCellValueExplicit([$col, $row], (float) $cell['num'], DataType::TYPE_NUMERIC);
            $sheet->getStyle([$col, $row])->getNumberFormat()->setFormatCode('#,##0.00');

            return;
        }
        $sheet->setCellValueExplicit([$col, $row], $this->s($cell['text'] ?? ''), DataType::TYPE_STRING);
    }

    /** Sanitasi anti formula-injeksi: beri prefix apostrof bila diawali =,+,-,@. */
    private function s(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function filename(string $type, array $data, string $ext): string
    {
        $unit = $data['unit'] === 'Konsolidasi (semua unit)' ? 'semua-unit' : Str::slug($data['unit']);
        $periode = Carbon::parse($data['to'])->format('Y-m');

        return "{$type}_{$periode}_{$unit}.{$ext}";
    }
}
