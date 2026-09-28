<?php

namespace App\Models;

use App\Enums\MutasiStokJenis;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutasiStok extends Model
{
    use HasFactory;

    protected $table = 'mutasi_stok';

    public const UPDATED_AT = null; // immutable ledger stok

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'urutan' => 'integer',
            'jenis' => MutasiStokJenis::class,
            'kuantitas_masuk' => 'decimal:4',
            'kuantitas_keluar' => 'decimal:4',
            'nilai_masuk' => 'decimal:2',
            'nilai_keluar' => 'decimal:2',
            'harga_pokok' => 'decimal:6',
            'saldo_kuantitas' => 'decimal:4',
            'saldo_nilai' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'produk_id');
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function transaksiBarang(): BelongsTo
    {
        return $this->belongsTo(TransaksiBarang::class, 'transaksi_barang_id');
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
