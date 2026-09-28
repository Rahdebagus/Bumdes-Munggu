<?php

namespace App\Domain\MasterData;

use App\Models\MutasiStok;
use App\Models\Produk;
use Illuminate\Validation\ValidationException;

class SaveProduct
{
    /**
     * Hanya field master yang boleh diedit. stok_terkini & harga_pokok_rata
     * adalah cache service (dokumen04) dan TIDAK pernah ditulis dari form.
     * Unit produk tidak dapat diganti setelah ada mutasi stok.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Produk $produk = null): Produk
    {
        unset($data['stok_terkini'], $data['harga_pokok_rata'], $data['stock_version']);

        if (! $produk) {
            return Produk::create($data);
        }

        if (array_key_exists('unit_usaha_id', $data)
            && (int) $data['unit_usaha_id'] !== (int) $produk->unit_usaha_id
            && MutasiStok::where('produk_id', $produk->id)->exists()) {
            throw ValidationException::withMessages([
                'form.unit_usaha_id' => 'Produk sudah memiliki mutasi stok; unit tidak dapat diganti.',
            ]);
        }

        $produk->update($data);

        return $produk;
    }
}
