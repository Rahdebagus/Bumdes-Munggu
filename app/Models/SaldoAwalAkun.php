<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaldoAwalAkun extends Model
{
    use HasFactory;

    protected $table = 'saldo_awal_akun';

    // scope_unit_id adalah generated STORED column: jangan di-assign.
    protected $guarded = ['id', 'scope_unit_id'];

    protected function casts(): array
    {
        return [
            'saldo_debit' => 'decimal:2',
            'saldo_kredit' => 'decimal:2',
            'locked_at' => 'datetime',
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

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function rincian(): HasMany
    {
        return $this->hasMany(SaldoAwalRincian::class, 'saldo_awal_akun_id');
    }
}
