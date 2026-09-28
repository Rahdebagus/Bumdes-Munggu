<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlokasiPelunasan extends Model
{
    use HasFactory;

    protected $table = 'alokasi_pelunasan';

    public const UPDATED_AT = null;

    // target_key adalah generated STORED column.
    protected $guarded = ['id', 'target_key'];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function pembayaranTransaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'pembayaran_transaksi_id');
    }

    public function invoiceTransaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'invoice_transaksi_id');
    }

    public function openingRincian(): BelongsTo
    {
        return $this->belongsTo(SaldoAwalRincian::class, 'opening_rincian_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
