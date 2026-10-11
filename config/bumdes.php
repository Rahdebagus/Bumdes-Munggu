<?php

/*
|--------------------------------------------------------------------------
| Identitas BUMDes (kop laporan & tanda tangan)
|--------------------------------------------------------------------------
| Dipakai pada kop laporan keuangan (HTML/cetak). Nilai ini bagian dari
| konfigurasi, bukan hardcode di view, sehingga mudah disesuaikan pengelola.
*/

return [
    'nama' => env('BUMDES_NAMA', 'BUMDes Munggu'),
    'badan' => env('BUMDES_BADAN', 'Badan Usaha Milik Desa'),
    'desa' => env('BUMDES_DESA', 'Desa Munggu'),
    'kecamatan' => env('BUMDES_KECAMATAN', 'Kec. Mengwi'),
    'kabupaten' => env('BUMDES_KABUPATEN', 'Kab. Badung'),
    'provinsi' => env('BUMDES_PROVINSI', 'Bali'),
    'kota_ttd' => env('BUMDES_KOTA_TTD', 'Munggu'),
    'jabatan_ttd' => env('BUMDES_JABATAN_TTD', 'Pengelola BUMDes Munggu'),
];
