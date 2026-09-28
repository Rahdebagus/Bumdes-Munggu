<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AkunJenis: string
{
    use HasValues;

    case Aset = 'aset';
    case Kewajiban = 'kewajiban';
    case Modal = 'modal';
    case Pendapatan = 'pendapatan';
    case Hpp = 'hpp';
    case Beban = 'beban';
}
