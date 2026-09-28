<?php

namespace App\Models;

use App\Enums\StokOpnameStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokOpname extends Model
{
    use HasFactory;

    protected $table = 'stok_opname';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StokOpnameStatus::class,
            'nilai_selisih' => 'decimal:2',
            'posted_at' => 'datetime',
            'approved_at' => 'datetime',
            'counted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'revision' => 'integer',
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

    public function details(): HasMany
    {
        return $this->hasMany(StokOpnameDetail::class, 'stok_opname_id');
    }
}
