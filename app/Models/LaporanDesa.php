<?php

namespace App\Models;

use App\Enums\LaporanDesaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanDesa extends Model
{
    use HasFactory;

    protected $table = 'laporan_desa';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'omzet_dagang' => 'decimal:2',
            'pendapatan_kredit' => 'decimal:2',
            'pendapatan_lain' => 'decimal:2',
            'total_omzet' => 'decimal:2',
            'snapshot_data' => 'array',
            'status' => LaporanDesaStatus::class,
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'generated_at' => 'datetime',
            'tanggal_dikirim' => 'date',
            'tanggal_diterima' => 'date',
            'version' => 'integer',
            'data_revision' => 'integer',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkuntansi::class, 'periode_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }
}
