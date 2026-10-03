<?php

namespace App\Domain\FixedAssets;

use App\Enums\AsetStatus;
use App\Models\AsetTetap;
use App\Models\User;

/**
 * Mendaftarkan aset tetap. Residu ≤ perolehan, umur > 0 (CHECK DB). Aset migrasi
 * membawa akumulasi_awal. Perolehan kas/kredit dicatat sebagai transaksi kas
 * terpisah dengan akun lawan aset (dokumen08 §7); register tidak menggandakan jurnal.
 */
class RegisterAsset
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): AsetTetap
    {
        return AsetTetap::create([
            'kode_aset' => $data['kode_aset'],
            'unit_usaha_id' => $data['unit_usaha_id'] ?? null,
            'nama_aset' => $data['nama_aset'],
            'tanggal_perolehan' => $data['tanggal_perolehan'],
            'harga_perolehan' => $data['harga_perolehan'],
            'nilai_residu' => $data['nilai_residu'] ?? 0,
            'umur_manfaat' => $data['umur_manfaat'],
            'metode_penyusutan' => 'garis_lurus',
            'status' => AsetStatus::Aktif->value,
            'akun_aset_id' => $data['akun_aset_id'] ?? null,
            'akun_akumulasi_id' => $data['akun_akumulasi_id'] ?? null,
            'akun_beban_id' => $data['akun_beban_id'] ?? null,
            'tanggal_mulai_susut' => $data['tanggal_mulai_susut'] ?? $data['tanggal_perolehan'],
            'akumulasi_awal' => $data['akumulasi_awal'] ?? 0,
        ]);
    }
}
