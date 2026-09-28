<?php

namespace App\Models;

use App\Enums\AsetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsetTetap extends Model
{
    use HasFactory;

    protected $table = 'aset_tetap';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal_perolehan' => 'date',
            'tanggal_mulai_susut' => 'date',
            'harga_perolehan' => 'decimal:2',
            'nilai_residu' => 'decimal:2',
            'umur_manfaat' => 'integer',
            'akumulasi_awal' => 'decimal:2',
            'status' => AsetStatus::class,
        ];
    }

    public function unitUsaha(): BelongsTo
    {
        return $this->belongsTo(UnitUsaha::class, 'unit_usaha_id');
    }

    public function akunAset(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_aset_id');
    }

    public function akunAkumulasi(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_akumulasi_id');
    }

    public function akunBeban(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_beban_id');
    }
}
