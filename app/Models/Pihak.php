<?php

namespace App\Models;

use App\Enums\PihakJenis;
use App\Enums\StatusUmum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
