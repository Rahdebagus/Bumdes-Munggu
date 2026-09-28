<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokOpnameDetail extends Model
{
    use HasFactory;

    protected $table = 'stok_opname_detail';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stok_sistem' => 'decimal:4',
            'stok_fisik' => 'decimal:4',
            'selisih' => 'decimal:4',
            'harga_pokok' => 'decimal:6',
            'nilai_selisih' => 'decimal:2',
            'stock_version_snapshot' => 'integer',
        ];
    }

    public function stokOpname(): BelongsTo
    {
        return $this->belongsTo(StokOpname::class, 'stok_opname_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
