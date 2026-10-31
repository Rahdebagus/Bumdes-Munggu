<?php

namespace App\Domain\MasterData;

use App\Enums\ProdukKategori;
use App\Models\MutasiStok;
use App\Models\Produk;
use App\Models\UnitUsaha;
use Illuminate\Validation\ValidationException;

class SaveProduct
{
    public function __construct(private ProductCode $codes) {}

    /**
     * Hanya field master yang boleh diedit. stok_terkini & harga_pokok_rata
     * adalah cache service (dokumen04) dan TIDAK pernah ditulis dari form.
     * Unit produk tidak dapat diganti setelah ada mutasi stok. Produk baru:
     * kode kosong → otomatis per kategori (KSM-001); unit kosong → unit
     * default kategori; lacak_stok tidak diisi → default kategori.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Produk $produk = null): Produk
    {
        unset($data['stok_terkini'], $data['harga_pokok_rata'], $data['stock_version']);

        $kategori = isset($data['kategori']) && $data['kategori'] !== ''
            ? ProdukKategori::tryFrom((string) $data['kategori'])
            : null;
        $data['kategori'] = $kategori?->value;

        if (! $produk) {
            if (blank($data['kode_produk'] ?? null)) {
                $data['kode_produk'] = $this->codes->next($kategori);
            }
            if (blank($data['unit_usaha_id'] ?? null)) {
                $data['unit_usaha_id'] = $this->defaultUnitId($kategori);
            }
            $data['lacak_stok'] ??= $kategori?->lacakStok() ?? true;

            return Produk::create($data);
        }

        if (array_key_exists('unit_usaha_id', $data)
            && (int) $data['unit_usaha_id'] !== (int) $produk->unit_usaha_id
            && MutasiStok::where('produk_id', $produk->id)->exists()) {
            throw ValidationException::withMessages([
                'form.unit_usaha_id' => 'Produk sudah memiliki mutasi stok; unit tidak dapat diganti.',
            ]);
        }

        if (array_key_exists('lacak_stok', $data)
            && (bool) $data['lacak_stok'] !== (bool) $produk->lacak_stok
            && MutasiStok::where('produk_id', $produk->id)->exists()) {
            throw ValidationException::withMessages([
                'form.lacak_stok' => 'Produk sudah memiliki mutasi stok; mode lacak stok tidak dapat diganti.',
            ]);
        }

        $produk->update($data);

        return $produk;
    }

    /** Unit usaha default untuk kategori (config product_categories.*.unit). */
    public function defaultUnitId(?ProdukKategori $kategori): ?int
    {
        $kode = $kategori ? config("accounting.product_categories.{$kategori->value}.unit") : null;

        return UnitUsaha::where('kode_unit', $kode ?? 'UNIT-DAGANG')->value('id')
            ?? UnitUsaha::where('jenis_usaha', 'dagang')->orderBy('id')->value('id');
    }
}
