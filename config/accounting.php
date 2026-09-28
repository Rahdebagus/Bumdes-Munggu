<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi akuntansi (mapping berversi) — dokumen05
|--------------------------------------------------------------------------
| system_key mengikat akun ke peran yang dipakai service. kategori_kode pada
| transaksi menyimpan KODE (bukan label); akun lawan dibatasi per kategori.
| Perubahan mapping TIDAK menulis ulang transaksi lama. Nilai di sini
| [TEKNIS/VALIDASI OPERASIONAL] — pengelola meninjau CoA & mapping sebelum
| data nyata (dokumen15 §Keputusan sebelum go-live).
*/

return [

    'version' => '2026.09',

    // Seluruh system_key yang WAJIB tersedia & aktif (dicek MasterDataSeeder).
    'system_keys' => [
        'cash',
        'bank',
        'inventory',
        'trade_receivable',
        'loan_receivable',
        'trade_payable',
        'capital',
        'sales',
        'interest_income',
        'penalty_income',
        'cogs',
        'inventory_loss',
        'inventory_gain',
        'fixed_asset',
        'accumulated_depreciation',
        'depreciation_expense',
        'retained_earnings',
        'tax_input',
        'tax_output',
        'operating_expense',
    ],

    // Kategori kas + akun lawan yang diizinkan (per system_key).
    'categories' => [
        'jasa' => ['label' => 'Pendapatan Jasa', 'arah' => 'masuk', 'lawan' => ['sales', 'interest_income']],
        'modal_masuk' => ['label' => 'Setoran Modal', 'arah' => 'masuk', 'lawan' => ['capital']],
        'modal_keluar' => ['label' => 'Penarikan Modal', 'arah' => 'keluar', 'lawan' => ['capital']],
        'beban' => ['label' => 'Beban Operasional', 'arah' => 'keluar', 'lawan' => ['operating_expense']],
        'pelunasan_piutang' => ['label' => 'Pelunasan Piutang', 'arah' => 'masuk', 'lawan' => ['trade_receivable', 'loan_receivable']],
        'pelunasan_utang' => ['label' => 'Pelunasan Utang', 'arah' => 'keluar', 'lawan' => ['trade_payable']],
        'perolehan_aset' => ['label' => 'Perolehan Aset', 'arah' => 'keluar', 'lawan' => ['fixed_asset']],
    ],

    // Kelompok laporan untuk akun (report_group).
    'report_groups' => [
        'aset_lancar', 'aset_tetap', 'kewajiban', 'ekuitas',
        'pendapatan', 'pendapatan_lain', 'hpp', 'beban',
    ],

    // Kelompok arus kas (transaksi_detail.kelompok_arus_kas).
    'arus_kas' => ['operasi', 'investasi', 'pendanaan'],
];
