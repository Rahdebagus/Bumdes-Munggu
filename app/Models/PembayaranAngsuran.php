<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembayaranAngsuran extends Model
{
    use HasFactory;

    protected $table = 'pembayaran_angsuran';

    public const UPDATED_AT = null; // immutable, hanya created_at

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pokok' => 'decimal:2',
            'bunga' => 'decimal:2',
            'denda' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function angsuran(): BelongsTo
    {
        return $this->belongsTo(Angsuran::class, 'angsuran_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
