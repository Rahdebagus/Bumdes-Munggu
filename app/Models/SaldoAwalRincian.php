<?php

namespace App\Models;

use App\Enums\SaldoRincianJenis;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaldoAwalRincian extends Model
{
    use HasFactory;

    protected $table = 'saldo_awal_rincian';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jenis' => SaldoRincianJenis::class,
            'tanggal_asal' => 'date',
            'jatuh_tempo' => 'date',
            'kuantitas' => 'decimal:4',
            'harga_pokok' => 'decimal:6',
            'nominal' => 'decimal:2',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function saldoAwalAkun(): BelongsTo
    {
        return $this->belongsTo(SaldoAwalAkun::class, 'saldo_awal_akun_id');
    }

    public function pihak(): BelongsTo
    {
        return $this->belongsTo(Pihak::class, 'pihak_id');
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function asetTetap(): BelongsTo
    {
        return $this->belongsTo(AsetTetap::class, 'aset_tetap_id');
    }

    public function pinjaman(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class, 'pinjaman_id');
    }
}
