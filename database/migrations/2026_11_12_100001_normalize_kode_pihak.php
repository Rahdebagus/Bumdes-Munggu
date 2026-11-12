<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kode pihak kini otomatis per jenis (PLG/PMS/PMJ-NNN, lihat PihakJenis::prefix).
 * Kode lama yang awalannya tidak sesuai jenis (mis. 001, CUST-001) diberi nomor
 * baru berurutan menurut id. Pihak direferensikan lewat id, jadi aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefixes = ['pelanggan' => 'PLG', 'pemasok' => 'PMS', 'peminjam' => 'PMJ'];

        foreach ($prefixes as $jenis => $prefix) {
            $max = DB::table('pihak')->where('kode_pihak', 'like', $prefix.'-%')->pluck('kode_pihak')
                ->map(fn (string $kode) => (int) substr($kode, strlen($prefix) + 1))
                ->max() ?? 0;

            DB::table('pihak')
                ->where('jenis_pihak', $jenis)
                ->where('kode_pihak', 'not like', $prefix.'-%')
                ->orderBy('id')
                ->pluck('id')
                ->each(function (int $id) use (&$max, $prefix) {
                    DB::table('pihak')->where('id', $id)->update(['kode_pihak' => sprintf('%s-%03d', $prefix, ++$max)]);
                });
        }
    }

    public function down(): void
    {
        // Kode lama tidak disimpan; penomoran ulang tidak dapat dibatalkan.
    }
};
