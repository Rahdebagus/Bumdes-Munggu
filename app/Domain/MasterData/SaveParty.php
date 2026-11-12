<?php

namespace App\Domain\MasterData;

use App\Enums\PihakJenis;
use App\Models\Pihak;

class SaveParty
{
    public function __construct(private PartyCode $codes) {}

    /**
     * Kode pihak selalu dari sistem (input diabaikan) dan mengikuti jenis:
     * PLG-001; jenis diganti → kode baru dengan awalan jenis baru.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Pihak $pihak = null): Pihak
    {
        $jenis = PihakJenis::from((string) ($data['jenis_pihak'] ?? $pihak?->jenis_pihak->value));
        $data['kode_pihak'] = $this->codes->for($pihak, $jenis);

        if ($pihak) {
            $pihak->update($data);

            return $pihak;
        }

        return Pihak::create($data);
    }
}
