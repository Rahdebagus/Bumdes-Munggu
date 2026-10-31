<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;

/**
 * Nota / bukti transaksi siap cetak (window.print) untuk satu dokumen:
 * penjualan, pembelian, kas, angsuran. Draft diberi cap "DRAFT".
 */
class TransactionNotaController extends Controller
{
    public function __invoke(Transaksi $transaksi)
    {
        $transaksi->load(['unitUsaha', 'pihak', 'barang.produk', 'details.akun', 'pembuat']);

        return view('transactions.nota', [
            'trx' => $transaksi,
            'org' => config('bumdes'),
            'kategori' => $transaksi->kategori_kode ? config("accounting.categories.{$transaksi->kategori_kode}.label") : null,
        ]);
    }
}
