<?php

namespace App\Domain\MasterData;

use App\Models\UnitUsaha;

class SaveUnit
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, ?UnitUsaha $unit = null): UnitUsaha
    {
        if ($unit) {
            $unit->update($data);

            return $unit;
        }

        return UnitUsaha::create($data);
    }
}
