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

    'version' => '2026.10',

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
        'sales_konsumsi',
        'sales_atk',
        'sales_upakara',
        'sales_pupuk',
        'interest_income',
        'penalty_income',
        'cogs',
        'cogs_konsumsi',
        'cogs_atk',
        'cogs_upakara',
        'cogs_pupuk',
        'inventory_loss',
        'inventory_gain',
        'fixed_asset',
        'accumulated_depreciation',
        'depreciation_expense',
        'retained_earnings',
        'tax_output',
        'operating_expense',
    ],

    /*
    | Kategori produk → akun penjualan & HPP (system_key), unit usaha default
    | (kode_unit), awalan kode produk, dan default lacak stok. Produk tanpa
    | kategori memakai 'sales'/'cogs' (perdagangan). Persediaan tetap perpetual:
    | jual = D HPP kategori / K Persediaan; jasa (lacak_stok=false) tanpa stok,
    | pembeliannya langsung D HPP kategori.
    */
    'product_categories' => [
        'konsumsi' => ['label' => 'Konsumsi / Jasa Boga', 'prefix' => 'KSM', 'unit' => 'UNIT-KONSUMSI', 'sales' => 'sales_konsumsi', 'cogs' => 'cogs_konsumsi', 'lacak_stok' => true],
        'perdagangan' => ['label' => 'Perdagangan Umum', 'prefix' => 'PDG', 'unit' => 'UNIT-DAGANG', 'sales' => 'sales', 'cogs' => 'cogs', 'lacak_stok' => true],
        'atk' => ['label' => 'ATK', 'prefix' => 'ATK', 'unit' => 'UNIT-ATK', 'sales' => 'sales_atk', 'cogs' => 'cogs_atk', 'lacak_stok' => true],
        'jasa' => ['label' => 'Jasa Cetak & Fotocopy', 'prefix' => 'JSA', 'unit' => 'UNIT-ATK', 'sales' => 'sales_atk', 'cogs' => 'cogs_atk', 'lacak_stok' => false],
        'upakara' => ['label' => 'Upakara / Banten', 'prefix' => 'UPK', 'unit' => 'UNIT-UPAKARA', 'sales' => 'sales_upakara', 'cogs' => 'cogs_upakara', 'lacak_stok' => true],
        'pupuk' => ['label' => 'Pupuk', 'prefix' => 'PPK', 'unit' => 'UNIT-PUPUK', 'sales' => 'sales_pupuk', 'cogs' => 'cogs_pupuk', 'lacak_stok' => true],
    ],

    /*
    | Kategori kas (Pemasukan & Pengeluaran) — mengikuti buku kas BUMDes.
    |   arah          masuk | keluar
    |   grup          utama | lainnya | sistem | legacy (sistem & legacy
    |                 disembunyikan dari form; sistem dipakai halaman khusus,
    |                 legacy tetap valid untuk transaksi lama/tes)
    |   default_lawan kode_akun yang otomatis terpilih sebagai akun lawan
    |   lawan         system_key akun lawan yang diizinkan
    |   lawan_kode    kode_akun tambahan yang diizinkan
    |   lawan_jenis   jenis_akun yang diizinkan (aset/kewajiban/modal/pendapatan/hpp/beban)
    |   lawan_kas     true → lawan boleh akun kas/bank lain (pemindahan dana)
    |   kas           system_key akun kas/bank default (cash | bank)
    |   arus          kelompok arus kas (operasi|investasi|pendanaan); null =
    |                 pemindahan antar kas/bank (tidak dihitung arus kas)
    |   redirect      kategori ini dicatat lewat menu lain (route + label)
    */
    'categories' => [
        // ---- Kas masuk --------------------------------------------------------
        'tarik_bank' => ['label' => 'Penambahan Kas dari Bank', 'arah' => 'masuk', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '1.1.01.02', 'lawan_kas' => true, 'arus' => null],
        'setoran_pedagang_pokok' => ['label' => 'Setoran Pedagang Pantai (Pokok)', 'arah' => 'masuk', 'grup' => 'utama', 'redirect' => ['route' => 'payments.index', 'label' => 'Pembayaran Angsuran', 'pesan' => 'Setoran pokok pedagang pantai dicatat di menu Pembayaran Angsuran agar jadwal & sisa pinjaman ikut ter-update (D Kas — K Piutang Pedagang Pantai).']],
        'bunga_pedagang' => ['label' => 'Bunga Pedagang Pantai', 'arah' => 'masuk', 'grup' => 'utama', 'redirect' => ['route' => 'payments.index', 'label' => 'Pembayaran Angsuran', 'pesan' => 'Bunga pedagang pantai dicatat bersama angsuran di menu Pembayaran Angsuran (K Pendapatan Bunga Pedagang Pantai).']],
        'bunga_bank' => ['label' => 'Pendapatan Bunga Bank', 'arah' => 'masuk', 'grup' => 'utama', 'kas' => 'bank', 'default_lawan' => '4.4.1', 'lawan_jenis' => ['pendapatan']],
        'pendapatan_wisata' => ['label' => 'Pendapatan Desa Wisata', 'arah' => 'masuk', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '4.4.2', 'lawan_jenis' => ['pendapatan']],
        'fee_pupuk' => ['label' => 'Pendapatan Fee Pupuk', 'arah' => 'masuk', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '4.4.3', 'lawan_jenis' => ['pendapatan']],
        'pelunasan_piutang' => ['label' => 'Pelunasan Piutang Usaha', 'arah' => 'masuk', 'grup' => 'utama', 'kas' => 'cash', 'lawan' => ['trade_receivable', 'loan_receivable']],
        'pendapatan_lain' => ['label' => 'Pendapatan Lain-lain', 'arah' => 'masuk', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '4.4.8', 'lawan_jenis' => ['pendapatan']],
        'modal_masuk' => ['label' => 'Setoran Modal', 'arah' => 'masuk', 'grup' => 'lainnya', 'kas' => 'bank', 'lawan' => ['capital'], 'lawan_jenis' => ['modal'], 'arus' => 'pendanaan'],
        'penjualan_barang' => ['label' => 'Penjualan barang/jasa', 'arah' => 'masuk', 'grup' => 'lainnya', 'redirect' => ['route' => 'sales.create', 'label' => 'Penjualan', 'pesan' => 'Penjualan barang/jasa dicatat di menu Penjualan agar stok berkurang dan pendapatan masuk ke akun penjualan yang benar.']],

        // ---- Kas keluar -------------------------------------------------------
        'biaya_pegawai' => ['label' => 'Biaya Pegawai', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '6.1.1', 'lawan_jenis' => ['beban'], 'lawan_kode' => ['2.1.00.02']],
        'biaya_transport' => ['label' => 'Biaya Transport', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '6.1.2', 'lawan_jenis' => ['beban']],
        'biaya_kantor' => ['label' => 'Biaya Kantor/ATK', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '6.1.3', 'lawan_jenis' => ['beban']],
        'biaya_penyusutan' => ['label' => 'Biaya Penyusutan', 'arah' => 'keluar', 'grup' => 'utama', 'redirect' => ['route' => 'depreciation.index', 'label' => 'Penyusutan Aset', 'pesan' => 'Penyusutan bukan pengeluaran kas (D Biaya Penyusutan — K Akm Penyusutan). Catat lewat menu Penyusutan Aset.']],
        'pajak_bunga_bank' => ['label' => 'Pajak Bunga Bank', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'bank', 'default_lawan' => '6.2.1', 'lawan_jenis' => ['beban']],
        'admin_bank' => ['label' => 'Administrasi Bank', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'bank', 'default_lawan' => '6.2.2', 'lawan_jenis' => ['beban']],
        'dana_sosial' => ['label' => 'Dana Sosial', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '3.2.10.00', 'lawan_jenis' => ['modal'], 'arus' => 'pendanaan'],
        'bpjs' => ['label' => 'BPJS Ketenagakerjaan', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '6.1.5', 'lawan_jenis' => ['beban']],
        'pelunasan_utang' => ['label' => 'Pembayaran Hutang Usaha', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'cash', 'lawan' => ['trade_payable']],
        'pencairan_pinjaman' => ['label' => 'Pencairan Pinjaman Pedagang', 'arah' => 'keluar', 'grup' => 'utama', 'redirect' => ['route' => 'loans.create', 'label' => 'Pinjaman Baru', 'pesan' => 'Pencairan pinjaman pedagang pantai dicatat di menu Data Pinjaman agar jadwal angsuran terbentuk (D Piutang Pedagang Pantai — K Kas/Bank).']],
        'setor_bank' => ['label' => 'Setoran ke Bank', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '1.1.01.02', 'lawan_kas' => true, 'arus' => null],
        'biaya_lain' => ['label' => 'Biaya Lain-lain', 'arah' => 'keluar', 'grup' => 'utama', 'kas' => 'cash', 'default_lawan' => '6.1.9', 'lawan_jenis' => ['beban']],
        'modal_keluar' => ['label' => 'Penarikan Modal', 'arah' => 'keluar', 'grup' => 'lainnya', 'kas' => 'bank', 'lawan' => ['capital'], 'lawan_jenis' => ['modal'], 'arus' => 'pendanaan'],
        'perolehan_aset' => ['label' => 'Perolehan Aset', 'arah' => 'keluar', 'grup' => 'lainnya', 'kas' => 'cash', 'lawan' => ['fixed_asset'], 'arus' => 'investasi'],
        'pembelian_barang' => ['label' => 'Pembelian barang dagangan', 'arah' => 'keluar', 'grup' => 'lainnya', 'redirect' => ['route' => 'purchases.create', 'label' => 'Pembelian', 'pesan' => 'Pembelian barang dagangan dicatat di menu Pembelian agar stok & persediaan bertambah.']],

        // ---- Sistem (dicatat dari halaman khusus, bukan form kas) ------------
        // Setoran borongan pinjaman pedagang lama (rincian saldo awal jenis
        // pinjaman) dari menu Pembayaran Angsuran: D Kas — K Piutang Pedagang
        // Pantai (pokok) + K Pendapatan Bunga/Denda.
        'setoran_pinjaman_lama' => ['label' => 'Setoran Pinjaman Pedagang Lama', 'arah' => 'masuk', 'grup' => 'sistem', 'kas' => 'cash', 'lawan' => ['loan_receivable']],

        // ---- Legacy (disembunyikan dari form; transaksi lama tetap valid) -----
        'jasa' => ['label' => 'Pendapatan Jasa', 'arah' => 'masuk', 'grup' => 'legacy', 'lawan' => ['sales', 'interest_income']],
        'beban' => ['label' => 'Beban Operasional', 'arah' => 'keluar', 'grup' => 'legacy', 'lawan' => ['operating_expense']],
    ],

    // Kelompok laporan untuk akun (report_group). 'beban_pajak' = pajak usaha
    // (ditampilkan setelah laba sebelum pajak), tetap ber-jenis 'beban'.
    // 'beban_non_operasional' = beban bank & selisih persediaan (jenis 'beban').
    'report_groups' => [
        'aset_lancar', 'aset_tetap', 'kewajiban', 'ekuitas',
        'pendapatan', 'pendapatan_lain', 'hpp', 'beban', 'beban_non_operasional', 'beban_pajak',
    ],

    // Kelompok arus kas (transaksi_detail.kelompok_arus_kas).
    'arus_kas' => ['operasi', 'investasi', 'pendanaan'],
];
