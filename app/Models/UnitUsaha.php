<?php

namespace App\Models;

use App\Enums\StatusUmum;
use App\Enums\UnitJenisUsaha;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitUsaha extends Model
{
    use HasFactory;

    protected $table = 'unit_usaha';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jenis_usaha' => UnitJenisUsaha::class,
            'status' => StatusUmum::class,
        ];
    }

    public function produk(): HasMany
    {
        return $this->hasMany(Produk::class);
    }

    public function pinjaman(): HasMany
    {
        return $this->hasMany(Pinjaman::class);
    }
}
