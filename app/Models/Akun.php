<?php

namespace App\Models;

use App\Enums\AkunJenis;
use App\Enums\SaldoNormal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Akun extends Model
{
    use HasFactory;

    protected $table = 'akun';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jenis_akun' => AkunJenis::class,
            'saldo_normal' => SaldoNormal::class,
            'is_active' => 'boolean',
            'is_cash' => 'boolean',
        ];
    }
}
