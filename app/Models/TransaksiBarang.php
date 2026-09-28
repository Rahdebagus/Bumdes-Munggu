<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiBarang extends Model
{
    use HasFactory;

    protected $table = 'transaksi_barang';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kuantitas' => 'decimal:4',
            'harga' => 'decimal:2',
            'harga_pokok' => 'decimal:6',
            'diskon' => 'decimal:2',
            'pajak' => 'decimal:2',
            'nilai_pokok' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'nomor_baris' => 'integer',
        ];
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }
}
