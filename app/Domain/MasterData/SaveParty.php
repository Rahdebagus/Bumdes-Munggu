<?php

namespace App\Domain\MasterData;

use App\Models\Pihak;

class SaveParty
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, ?Pihak $pihak = null): Pihak
    {
        if ($pihak) {
            $pihak->update($data);

            return $pihak;
        }

        return Pihak::create($data);
    }
}
