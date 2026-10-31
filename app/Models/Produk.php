<?php

namespace App\Models;

use App\Enums\ProdukKategori;
use App\Enums\StatusUmum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produk extends Model
{
    use HasFactory;

    protected $table = 'produk';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kategori' => ProdukKategori::class,
            'lacak_stok' => 'boolean',
            'harga_beli' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'harga_jual_perlu_verifikasi' => 'boolean',
            'stok_terkini' => 'decimal:4',
            'stok_minimum' => 'decimal:4',
            'harga_pokok_rata' => 'decimal:6',
            'stock_version' => 'integer',
            'status' => StatusUmum::class,
        ];
    }

    public function unitUsaha(): BelongsTo
    {
        return $this->belongsTo(UnitUsaha::class, 'unit_usaha_id');
    }

    /** system_key akun penjualan sesuai kategori (default 'sales'). */
    public function salesKey(): string
    {
        return config('accounting.product_categories.'.$this->kategori?->value.'.sales', 'sales');
    }

    /** system_key akun HPP sesuai kategori (default 'cogs'). */
    public function cogsKey(): string
    {
        return config('accounting.product_categories.'.$this->kategori?->value.'.cogs', 'cogs');
    }
}
