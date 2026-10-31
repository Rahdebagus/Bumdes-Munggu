<?php

namespace App\Http\Controllers;

use App\Domain\Reporting\ReportExporter;
use App\Models\LaporanDesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Unduh laporan omzet desa RESMI dari snapshot tersimpan (bukan live query,
 * dokumen10 §Alur snapshot langkah 5). File privat (disk local) dihapus
 * setelah terkirim; snapshot resminya tetap di tabel laporan_desa.
 */
class LaporanDesaExportController extends Controller
{
    public function __invoke(Request $request, LaporanDesa $laporan, ReportExporter $exporter)
    {
        $format = $request->validate(['format' => ['required', 'in:pdf,xlsx']])['format'];

        $result = $format === 'pdf'
            ? $exporter->laporanDesaPdf($laporan)
            : $exporter->laporanDesaXlsx($laporan);

        return response()
            ->download(Storage::disk('local')->path($result['path']), $result['filename'])
            ->deleteFileAfterSend();
    }
}
