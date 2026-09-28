<?php

namespace App\Models;

use App\Enums\BasisBunga;
use App\Enums\PinjamanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pinjaman extends Model
{
    use HasFactory;

    protected $table = 'pinjaman';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal_pinjaman' => 'date',
            'tanggal_angsuran_pertama' => 'date',
            'pokok_pinjaman' => 'decimal:2',
            'persentase_bunga' => 'decimal:2',
            'tenor' => 'integer',
            'total_bunga' => 'decimal:2',
            'sisa_pokok' => 'decimal:2',
            'status' => PinjamanStatus::class,
            'basis_bunga' => BasisBunga::class,
        ];
    }

    public function unitUsaha(): BelongsTo
    {
        return $this->belongsTo(UnitUsaha::class, 'unit_usaha_id');
    }

    public function peminjam(): BelongsTo
    {
        return $this->belongsTo(Pihak::class, 'peminjam_id');
    }

    public function angsuran(): HasMany
    {
        return $this->hasMany(Angsuran::class, 'pinjaman_id');
    }

    public function transaksiPencairan(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }
}
