<?php

namespace App\Models;

use App\Enums\AngsuranStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Angsuran extends Model
{
    use HasFactory;

    protected $table = 'angsuran';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'angsuran_ke' => 'integer',
            'tanggal_jatuh_tempo' => 'date',
            'pokok_tagihan' => 'decimal:2',
            'bunga_tagihan' => 'decimal:2',
            'denda_tagihan' => 'decimal:2',
            'pokok_dibayar' => 'decimal:2',
            'bunga_dibayar' => 'decimal:2',
            'denda_dibayar' => 'decimal:2',
            'tanggal_bayar' => 'date',
            'status' => AngsuranStatus::class,
            'version' => 'integer',
        ];
    }

    public function pinjaman(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class, 'pinjaman_id');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(PembayaranAngsuran::class, 'angsuran_id');
    }
}
