<?php

namespace App\Models;

use App\Enums\PihakJenis;
use App\Enums\StatusUmum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pihak extends Model
{
    use HasFactory;

    protected $table = 'pihak';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jenis_pihak' => PihakJenis::class,
            'status' => StatusUmum::class,
        ];
    }

    /** Pinjaman di mana pihak ini sebagai peminjam. */
    public function pinjaman(): HasMany
    {
        return $this->hasMany(Pinjaman::class, 'peminjam_id');
    }
}
