<?php

namespace App\Domain\MasterData;

use App\Enums\PihakJenis;
use App\Models\Pihak;

/**
 * Kode pihak selalu dibuat sistem dari jenis: PLG-001 (pelanggan), PMS-001
 * (pemasok), PMJ-001 (peminjam). Nomor berikutnya = nomor terbesar yang sudah
 * dipakai awalan itu + 1.
 */
class PartyCode
{
    public function next(PihakJenis $jenis): string
    {
        $prefix = $jenis->prefix();

        $max = Pihak::where('kode_pihak', 'like', $prefix.'-%')
            ->pluck('kode_pihak')
            ->map(fn (string $kode) => (int) substr($kode, strlen($prefix) + 1))
            ->max() ?? 0;

        return sprintf('%s-%03d', $prefix, $max + 1);
    }

    /** Kode lama dipertahankan bila awalannya cocok; selain itu nomor baru. */
    public function for(?Pihak $pihak, PihakJenis $jenis): string
    {
        if ($pihak && str_starts_with($pihak->kode_pihak, $jenis->prefix().'-')) {
            return $pihak->kode_pihak;
        }

        return $this->next($jenis);
    }
}
