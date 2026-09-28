<?php

namespace App\Models;

use App\Enums\TransaksiJenis;
use App\Enums\TransaksiStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaksi extends Model
{
    use HasFactory;

    protected $table = 'transaksi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jenis_transaksi' => TransaksiJenis::class,
            'status' => TransaksiStatus::class,
            'total' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'diskon_total' => 'decimal:2',
            'pajak_total' => 'decimal:2',
            'jatuh_tempo' => 'date',
            'reversal_date' => 'date',
            'posted_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkuntansi::class, 'periode_id');
    }

    public function unitUsaha(): BelongsTo
    {
        return $this->belongsTo(UnitUsaha::class, 'unit_usaha_id');
    }

    public function pihak(): BelongsTo
    {
        return $this->belongsTo(Pihak::class, 'pihak_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransaksiDetail::class, 'transaksi_id');
    }

    public function barang(): HasMany
    {
        return $this->hasMany(TransaksiBarang::class, 'transaksi_id');
    }

    public function bukti(): HasMany
    {
        return $this->hasMany(BuktiTransaksi::class, 'transaksi_id');
    }
}
