@php
    // Struktur menu dokumen07. Setiap item memetakan ke route nyata; item tanpa
    // route ditandai "segera" (disabled) secara jujur sampai modulnya dibangun.
    // Kunci 'icon' memilih garis ikon dari peta di bawah; 'badge' (opsional) diisi
    // dari data dinamis saat tersedia — jangan hardcode angka agar tidak menyesatkan.
    $groups = [
        'Dashboard' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard'],
        ],
        'Transaksi' => [
            ['label' => 'Penjualan', 'route' => 'sales.create', 'icon' => 'cart'],
            ['label' => 'Pembelian', 'route' => 'purchases.create', 'icon' => 'bag'],
            ['label' => 'Pemasukan & Pengeluaran', 'route' => 'cash.create', 'icon' => 'exchange'],
            ['label' => 'Pembayaran Angsuran', 'route' => 'payments.index', 'icon' => 'credit-card'],
            ['label' => 'Daftar Transaksi', 'route' => 'transactions.index', 'icon' => 'list'],
        ],
        'Usaha Kredit' => [
            ['label' => 'Data Peminjam', 'route' => 'borrowers.index', 'icon' => 'users'],
            ['label' => 'Data Pinjaman', 'route' => 'loans.index', 'icon' => 'bank'],
            ['label' => 'Jadwal Angsuran', 'route' => 'schedules.index', 'icon' => 'calendar'],
        ],
        'Data Master' => [
            ['label' => 'Unit Usaha', 'route' => 'master.units', 'icon' => 'building'],
            ['label' => 'Akun Akuntansi', 'route' => 'master.accounts', 'icon' => 'book'],
            ['label' => 'Data Pihak', 'route' => 'master.parties', 'icon' => 'id-card'],
            ['label' => 'Data Produk', 'route' => 'master.products', 'icon' => 'tag'],
            ['label' => 'Saldo Awal', 'route' => 'opening-balance', 'icon' => 'wallet'],
        ],
        'Persediaan' => [
            ['label' => 'Stok Barang', 'route' => 'inventory.stock', 'icon' => 'cube'],
            ['label' => 'Stok Opname', 'route' => 'stocktakes.index', 'icon' => 'clipboard-check'],
        ],
        'Akuntansi' => [
            ['label' => 'Jurnal Transaksi', 'route' => 'transactions.index', 'icon' => 'journal'],
            ['label' => 'Jurnal Penyesuaian', 'route' => 'accounting.manual-journal', 'icon' => 'edit'],
            ['label' => 'Buku Besar', 'route' => 'reports.index', 'params' => ['type' => 'buku-besar'], 'icon' => 'ledger'],
            ['label' => 'Periode Akuntansi', 'route' => 'periods.index', 'icon' => 'calendar-check'],
        ],
        'Aset' => [
            ['label' => 'Data Aset Tetap', 'route' => 'assets.index', 'icon' => 'office'],
            ['label' => 'Penyusutan Aset', 'route' => 'depreciation.index', 'icon' => 'trending-down'],
        ],
        'Laporan Keuangan' => [
            ['label' => 'Laba Rugi', 'route' => 'reports.index', 'params' => ['type' => 'laba-rugi'], 'icon' => 'chart-bar'],
            ['label' => 'Neraca', 'route' => 'reports.index', 'params' => ['type' => 'neraca'], 'icon' => 'scale'],
            ['label' => 'Perubahan Modal', 'route' => 'reports.index', 'params' => ['type' => 'perubahan-modal'], 'icon' => 'trending-up'],
            ['label' => 'Arus Kas', 'route' => 'reports.index', 'params' => ['type' => 'arus-kas'], 'icon' => 'cash'],
            ['label' => 'Laporan Kredit', 'route' => 'reports.index', 'params' => ['type' => 'kredit'], 'icon' => 'percent'],
            ['label' => 'Laporan Omzet Desa', 'route' => 'reports.laporan-desa', 'icon' => 'storefront'],
        ],
        'Pengawasan' => [
            ['label' => 'Bukti Transaksi', 'route' => 'oversight.evidence', 'icon' => 'paperclip'],
            ['label' => 'Histori Aktivitas', 'route' => 'oversight.activity', 'icon' => 'history'],
        ],
    ];

    // Peta ikon (garis/outline, viewBox 24). Mengembalikan isi <path> untuk dibungkus <svg>.
    $icon = function (string $name): string {
        return match ($name) {
            'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />',
            'cart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />',
            'bag' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z" />',
            'exchange' => '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />',
            'credit-card' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 7.5h3m-3 2.25h6m-9 0h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />',
            'list' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h7.5M3.75 6.75h.007v.008H3.75V6.75Zm0 5.25h.007v.008H3.75V12Zm0 5.25h.007v.008H3.75v-.008Z" />',
            'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />',
            'bank' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z" />',
            'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />',
            'building' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />',
            'book' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />',
            'id-card' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
            'tag' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />',
            'wallet' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" />',
            'cube' => '<path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />',
            'clipboard-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />',
            'journal' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
            'edit' => '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />',
            'ledger' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m-17.25-3.75h7.5m-7.5 0v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5c0-.621.504-1.125 1.125-1.125" />',
            'calendar-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 2.994v2.25m10.5-2.25v2.25m-14.252 13.5V7.491a2.25 2.25 0 0 1 2.25-2.25h13.5a2.25 2.25 0 0 1 2.25 2.25v11.251m-18 0a2.25 2.25 0 0 0 2.25 2.25h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5a2.25 2.25 0 0 1 2.25-2.25h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5m-6.75-6 1.5 1.5 3-3.75" />',
            'office' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />',
            'trending-down' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181" />',
            'chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />',
            'scale' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />',
            'trending-up' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.519l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />',
            'cash' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />',
            'percent' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Zm-9.75 2.243h.008v.008H9.75V9Zm3.75 5.25h.008v.008h-.008v-.008Z" />',
            'storefront' => '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 0a3.001 3.001 0 0 1-3-.615" />',
            'paperclip' => '<path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />',
            'history' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
            'logout' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />',
            default => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />',
        };
    };
@endphp

<aside
    x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-[#0b3b2c] text-emerald-50 transition-transform duration-200 ease-in-out"
    aria-label="Navigasi utama">
    {{-- Merek --}}
    <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-emerald-400 to-emerald-600 shadow-sm">
            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M5 20a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1H5Zm6 0a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-2Zm6 0a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1h-2Z" />
            </svg>
        </span>
        <span class="min-w-0 leading-tight">
            <span class="block truncate text-sm font-bold text-white">SIA BUMDes</span>
            <span class="block text-[11px] font-medium uppercase tracking-wider text-emerald-300/70">Munggu</span>
        </span>
    </div>

    {{-- Navigasi --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-white/10 [&::-webkit-scrollbar-track]:bg-transparent">
        @foreach ($groups as $group => $items)
            @unless ($group === 'Dashboard')
                <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider text-white/35">{{ $group }}</p>
            @endunless
            <ul class="space-y-0.5">
                @foreach ($items as $item)
                    @php
                        $hasRoute = isset($item['route']) && \Illuminate\Support\Facades\Route::has($item['route']);
                        $isActive = $hasRoute
                            && request()->routeIs($item['route'])
                            && (! isset($item['params']['type']) || request()->query('type', 'laba-rugi') === $item['params']['type']);
                    @endphp
                    <li>
                        @if ($hasRoute)
                            <a href="{{ route($item['route'], $item['params'] ?? []) }}" wire:navigate
                               @class([
                                   'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
                                   'bg-emerald-600 font-medium text-white shadow-sm' => $isActive,
                                   'text-emerald-50/75 hover:bg-white/10 hover:text-white' => ! $isActive,
                               ])
                               @if($isActive) aria-current="page" @endif>
                                <svg class="h-5 w-5 shrink-0 {{ $isActive ? 'text-white' : 'text-emerald-300/80 group-hover:text-white' }}"
                                     fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                    {!! $icon($item['icon'] ?? 'default') !!}
                                </svg>
                                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                @isset($item['badge'])
                                    <span class="ml-auto rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $isActive ? 'bg-white/20 text-white' : 'bg-emerald-500/20 text-emerald-100' }}">
                                        {{ $item['badge'] }}
                                    </span>
                                @endisset
                            </a>
                        @else
                            <span class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-emerald-50/30"
                                  title="Segera — modul belum dibangun">
                                <svg class="h-5 w-5 shrink-0 text-emerald-50/25" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                                    {!! $icon($item['icon'] ?? 'default') !!}
                                </svg>
                                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">segera</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>

    {{-- Keluar --}}
    @auth
        <div class="border-t border-white/10 p-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-emerald-50/75 transition-colors hover:bg-white/10 hover:text-white">
                    <svg class="h-5 w-5 shrink-0 text-emerald-300/80 group-hover:text-white" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                        {!! $icon('logout') !!}
                    </svg>
                    <span class="flex-1 text-left">Logout</span>
                </button>
            </form>
        </div>
    @endauth
</aside>
