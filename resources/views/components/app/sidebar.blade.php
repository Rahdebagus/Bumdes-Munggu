@php
    // Struktur menu dokumen07. Tahap01: hanya Dashboard yang aktif; menu lain
    // ditandai "segera" (disabled) secara jujur sampai modulnya dibangun.
    $groups = [
        'Dashboard' => [
            ['label' => 'Ringkasan Usaha', 'route' => 'dashboard'],
        ],
        'Transaksi' => [
            ['label' => 'Penjualan', 'route' => 'sales.create'],
            ['label' => 'Pembelian', 'route' => 'purchases.create'],
            ['label' => 'Kas (Pemasukan/Pengeluaran)', 'route' => 'cash.create'],
            ['label' => 'Daftar Transaksi', 'route' => 'transactions.index'],
        ],
        'Usaha Kredit' => [
            ['label' => 'Pinjaman', 'route' => 'loans.index'],
            ['label' => 'Jadwal & Pembayaran', 'route' => 'loans.index'],
        ],
        'Data Master' => [
            ['label' => 'Unit Usaha', 'route' => 'master.units'],
            ['label' => 'Akun', 'route' => 'master.accounts'],
            ['label' => 'Pihak', 'route' => 'master.parties'],
            ['label' => 'Produk', 'route' => 'master.products'],
            ['label' => 'Saldo Awal', 'route' => 'opening-balance'],
        ],
        'Persediaan' => [
            ['label' => 'Stok Barang', 'route' => 'inventory.stock'],
            ['label' => 'Kartu Stok', 'route' => 'inventory.stock'],
            ['label' => 'Stok Opname', 'route' => 'stocktakes.index'],
        ],
        'Akuntansi' => [
            ['label' => 'Jurnal Penyesuaian', 'route' => 'accounting.manual-journal'],
            ['label' => 'Jurnal'],
            ['label' => 'Buku Besar', 'route' => 'reports.index', 'params' => ['type' => 'buku-besar']],
            ['label' => 'Periode Akuntansi'],
        ],
        'Aset' => [
            ['label' => 'Aset Tetap', 'route' => 'assets.index'],
            ['label' => 'Penyusutan', 'route' => 'assets.index'],
        ],
        'Laporan' => [
            ['label' => 'Laba Rugi', 'route' => 'reports.index', 'params' => ['type' => 'laba-rugi']],
            ['label' => 'Neraca', 'route' => 'reports.index', 'params' => ['type' => 'neraca']],
            ['label' => 'Perubahan Modal', 'route' => 'reports.index', 'params' => ['type' => 'perubahan-modal']],
            ['label' => 'Arus Kas', 'route' => 'reports.index', 'params' => ['type' => 'arus-kas']],
            ['label' => 'Kredit', 'route' => 'reports.index', 'params' => ['type' => 'kredit']],
            ['label' => 'Omzet Desa', 'route' => 'reports.index', 'params' => ['type' => 'omzet-desa']],
        ],
        'Pengawasan' => [
            ['label' => 'Audit/Rekonsiliasi', 'route' => 'reports.index'],
            ['label' => 'Bukti Transaksi'], ['label' => 'Histori Aktivitas'],
        ],
    ];
@endphp

<aside
    x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-200 ease-in-out"
    aria-label="Navigasi utama">
    <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-sm font-bold text-white">B</span>
        <span class="text-sm font-semibold text-slate-900">SIA BUMDes Munggu</span>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        @foreach ($groups as $group => $items)
            <p class="px-2 pb-1 pt-3 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $group }}</p>
            <ul class="space-y-0.5">
                @foreach ($items as $item)
                    @php $hasRoute = isset($item['route']) && \Illuminate\Support\Facades\Route::has($item['route']); @endphp
                    <li>
                        @if ($hasRoute)
                            <a href="{{ route($item['route'], $item['params'] ?? []) }}" wire:navigate
                               @class([
                                   'flex items-center rounded-lg px-3 py-2 text-sm font-medium',
                                   'bg-emerald-50 text-emerald-700' => request()->routeIs($item['route']),
                                   'text-slate-700 hover:bg-slate-100' => ! request()->routeIs($item['route']),
                               ])
                               @if(request()->routeIs($item['route'])) aria-current="page" @endif>
                                {{ $item['label'] }}
                            </a>
                        @else
                            <span class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-400"
                                  title="Segera — modul belum dibangun">
                                {{ $item['label'] }}
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase">segera</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>
</aside>
