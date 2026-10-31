<?php

namespace App\Domain\MasterData;

use App\Enums\ProdukKategori;
use App\Models\Produk;

/**
 * Kode produk otomatis per kategori: AWALAN-NNN (mis. KSM-001, ATK-014).
 * Nomor berikutnya = nomor terbesar yang sudah dipakai awalan itu + 1.
 */
class ProductCode
{
    public function next(?ProdukKategori $kategori): string
    {
        $prefix = $kategori?->prefix() ?? 'PRD';

        $max = Produk::where('kode_produk', 'like', $prefix.'-%')
            ->pluck('kode_produk')
            ->map(fn (string $kode) => (int) substr($kode, strlen($prefix) + 1))
            ->max() ?? 0;

        return sprintf('%s-%03d', $prefix, $max + 1);
    }
}
