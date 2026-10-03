<?php

namespace App\Http\Controllers;

use App\Domain\Reporting\ReportDataset;
use App\Domain\Reporting\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Unduh laporan PDF/XLSX dari ReportExporter (dataset ReportService yang sama).
 * File disimpan privat (disk local), lalu di-stream sebagai attachment.
 */
class ReportExportController extends Controller
{
    public function __invoke(Request $request, ReportExporter $exporter)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(ReportDataset::TYPES))],
            'format' => ['required', 'in:pdf,xlsx'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'unitId' => ['nullable', 'integer', 'exists:unit_usaha,id'],
        ]);

        $filters = [
            'from' => $validated['from'],
            'to' => $validated['to'],
            'asOf' => $validated['to'],
            'unitId' => $validated['unitId'] ?? null,
        ];

        $result = $validated['format'] === 'pdf'
            ? $exporter->pdf($validated['type'], $filters)
            : $exporter->xlsx($validated['type'], $filters);

        return Storage::disk('local')->download($result['path'], $result['filename']);
    }
}
