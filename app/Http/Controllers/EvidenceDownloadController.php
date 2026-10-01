<?php

namespace App\Http\Controllers;

use App\Models\BuktiTransaksi;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh bukti dari disk private sebagai attachment. Route berada di grup
 * auth + user aktif; path tidak pernah diterima dari klien.
 */
class EvidenceDownloadController extends Controller
{
    public function __invoke(BuktiTransaksi $bukti): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($bukti->file_bukti), 404);

        return Storage::disk('local')->download($bukti->file_bukti, $bukti->original_name ?: 'bukti');
    }
}
