<?php

namespace App\Livewire\Forms;

use App\Enums\StatusUmum;
use App\Models\Produk;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ProductForm extends Form
{
    public ?int $produkId = null;

    public ?int $unit_usaha_id = null;

    public string $kode_produk = '';

    public string $nama_produk = '';

    public string $satuan = '';

    public string $harga_beli = '0';

    public string $harga_jual = '0';

    public string $stok_minimum = '0';

    public string $status = 'aktif';

    // Catatan: stok_terkini & harga_pokok_rata TIDAK diedit dari form (cache
    // service; dokumen04). Perubahan stok hanya lewat mutasi_stok.

    public function rules(): array
    {
        return [
            'unit_usaha_id' => ['required', 'integer', Rule::exists('unit_usaha', 'id')],
            'kode_produk' => ['required', 'string', 'max:20', Rule::unique('produk', 'kode_produk')->ignore($this->produkId)],
            'nama_produk' => ['required', 'string', 'max:100'],
            'satuan' => ['required', 'string', 'max:20'],
            'harga_beli' => ['required', 'numeric', 'min:0'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'stok_minimum' => ['required', 'numeric', 'min:0'],
            'status' => ['required', StatusUmum::rule()],
        ];
    }

    public function setProduct(Produk $produk): void
    {
        $this->produkId = $produk->id;
        $this->unit_usaha_id = $produk->unit_usaha_id;
        $this->kode_produk = $produk->kode_produk;
        $this->nama_produk = $produk->nama_produk;
        $this->satuan = $produk->satuan;
        $this->harga_beli = (string) $produk->harga_beli;
        $this->harga_jual = (string) $produk->harga_jual;
        $this->stok_minimum = (string) $produk->stok_minimum;
        $this->status = $produk->status->value;
    }
}
