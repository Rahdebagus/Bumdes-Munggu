<?php

namespace App\Models;

use App\Enums\VerifikasiStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuktiTransaksi extends Model
{
    use HasFactory;

    protected $table = 'bukti_transaksi';

    protected $guarded = ['id'];

    protected $hidden = ['file_bukti']; // path privat, jangan bocor ke frontend

    protected function casts(): array
    {
        return [
            'tanggal_bukti' => 'date',
            'status_verifikasi' => VerifikasiStatus::class,
            'verified_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
