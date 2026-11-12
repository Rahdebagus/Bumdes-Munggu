<?php

namespace App\Domain\MasterData;

use App\Enums\ProdukKategori;
use App\Models\Produk;

/**
 * Kode produk selalu dibuat sistem dari kategori: AWALAN-NNN (mis. KSM-001,
 * ATK-014), sehingga semua produk satu kategori berawalan sama. Nomor
 * berikutnya = nomor terbesar yang sudah dipakai awalan itu + 1.
 */
class ProductCode
{
    public function next(?ProdukKategori $kategori): string
    {
        $prefix = $this->prefix($kategori);

        $max = Produk::where('kode_produk', 'like', $prefix.'-%')
            ->pluck('kode_produk')
            ->map(fn (string $kode) => (int) substr($kode, strlen($prefix) + 1))
            ->max() ?? 0;

        return sprintf('%s-%03d', $prefix, $max + 1);
    }

    /**
     * Kode untuk produk pada kategori tsb: kode lama dipertahankan bila
     * awalannya sudah cocok; selain itu (produk baru / kategori diganti)
     * nomor baru dengan awalan kategori.
     */
    public function for(?Produk $produk, ?ProdukKategori $kategori): string
    {
        if ($produk && str_starts_with($produk->kode_produk, $this->prefix($kategori).'-')) {
            return $produk->kode_produk;
        }

        return $this->next($kategori);
    }

    private function prefix(?ProdukKategori $kategori): string
    {
        return $kategori?->prefix() ?? 'PRD';
    }
}
