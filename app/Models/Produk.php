<?php

namespace App\Models;

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
            'harga_beli' => 'decimal:2',
            'harga_jual' => 'decimal:2',
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
}
