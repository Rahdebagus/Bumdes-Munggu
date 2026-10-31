<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Kategori produk BUMDes. Pemetaan akun penjualan/HPP, unit usaha default, dan
 * awalan kode ada di config/accounting.php (product_categories).
 */
enum ProdukKategori: string
{
    use HasValues;

    case Konsumsi = 'konsumsi';
    case Perdagangan = 'perdagangan';
    case Atk = 'atk';
    case Upakara = 'upakara';
    case Jasa = 'jasa';
    case Pupuk = 'pupuk';

    public function label(): string
    {
        return config("accounting.product_categories.{$this->value}.label", ucfirst($this->value));
    }

    /** Awalan kode produk otomatis, mis. KSM-001. */
    public function prefix(): string
    {
        return config("accounting.product_categories.{$this->value}.prefix", strtoupper(substr($this->value, 0, 3)));
    }

    /** Default lacak stok untuk produk baru di kategori ini. */
    public function lacakStok(): bool
    {
        return (bool) config("accounting.product_categories.{$this->value}.lacak_stok", true);
    }
}
