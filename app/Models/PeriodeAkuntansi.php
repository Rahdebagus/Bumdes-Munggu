<?php

namespace App\Models;

use App\Enums\PeriodeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeAkuntansi extends Model
{
    use HasFactory;

    protected $table = 'periode_akuntansi';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'status' => PeriodeStatus::class,
            'closed_at' => 'datetime',
            'opening_locked_at' => 'datetime',
            'reopened_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function transaksi(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'periode_id');
    }
}
