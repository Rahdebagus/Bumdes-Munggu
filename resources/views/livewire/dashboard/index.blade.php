@php
    // Formatter lokal: rupiah 2 desimal (konsisten dengan laporan) & kuantitas trim.
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 2, ',', '.');
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');

    // Aksen warna kartu KPI (string literal agar ter-scan Tailwind).
    $accent = [
        'blue' => ['border' => 'border-t-blue-500', 'chip' => 'bg-blue-50 text-blue-600', 'value' => 'text-blue-700'],
        'emerald' => ['border' => 'border-t-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-600', 'value' => 'text-emerald-700'],
        'rose' => ['border' => 'border-t-rose-500', 'chip' => 'bg-rose-50 text-rose-600', 'value' => 'text-rose-700'],
        'amber' => ['border' => 'border-t-amber-500', 'chip' => 'bg-amber-50 text-amber-600', 'value' => 'text-amber-700'],
    ];

    $kpiIcon = fn ($k) => match ($k) {
        'wallet' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" />',
        'store' => '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 0a3.001 3.001 0 0 1-3-.615" />',
        'percent' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Zm-9.75 2.243h.008v.008H9.75V9Zm3.75 5.25h.008v.008h-.008v-.008Z" />',
        'trending-down' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181" />',
        'trending-up' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.307a11.95 11.95 0 0 1 5.814-5.519l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />',
        'coins' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        default => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />',
    };

    $periode = $this->periodeAktif();
    $rekon = $this->rekonsiliasiKas();
    $ringkasan = $this->ringkasanNeraca();
    $komposisi = $this->komposisi();
@endphp

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700" role="status">
            {{ session('status') }}
        </div>
    @endif

    {{-- Periode aktif + aksi cepat --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Periode Aktif</p>
            <div class="mt-1 flex items-center gap-2">
                <span class="text-lg font-semibold text-slate-900">
                    @if ($periode)
                        {{ ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][$periode->bulan] ?? $periode->bulan }}
                        {{ $periode->tahun }}
                    @else
                        Belum ada periode
                    @endif
                </span>
                @if ($periode)
                    <x-ui.badge :color="$periode->status->value === 'terbuka' ? 'emerald' : 'slate'">
                        {{ ucfirst($periode->status->value) }}
                    </x-ui.badge>
                @endif
            </div>
        </div>

        <div x-data="{ open: false }" class="relative">
            <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="true"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Tambah Transaksi
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" x-transition
                 class="absolute right-0 z-10 mt-2 w-56 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg" role="menu">
                <a href="{{ route('sales.create') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">Penjualan</a>
                <a href="{{ route('purchases.create') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">Pembelian</a>
                <a href="{{ route('cash.create') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">Pemasukan / Pengeluaran</a>
            </div>
        </div>
    </div>

    {{-- KPI --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->kpis() as $kpi)
            @php($a = $accent[$kpi['color']] ?? $accent['blue'])
            <x-ui.card class="border-t-4 {{ $a['border'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $kpi['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold tabular-nums {{ $a['value'] }}">{{ $kpi['value'] }}</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $a['chip'] }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">{!! $kpiIcon($kpi['icon']) !!}</svg>
                    </span>
                </div>
                <p class="mt-3 truncate text-xs text-slate-400" title="{{ $kpi['hint'] }}">{{ $kpi['hint'] }}</p>
            </x-ui.card>
        @endforeach
    </div>

    {{-- Komposisi pendapatan --}}
    <x-ui.card title="Komposisi Pendapatan{{ $periode ? ' '.(['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][$periode->bulan] ?? '').' '.$periode->tahun : '' }}"
               subtitle="Rincian sumber pendapatan periode berjalan">
        @if (empty($komposisi['rows']))
            <div class="flex h-24 items-center justify-center rounded-lg border border-dashed border-slate-200 text-sm text-slate-400">
                Komposisi tampil setelah ada pendapatan terposting.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($komposisi['rows'] as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-700">{{ $row['label'] }}</span>
                            <span class="font-semibold tabular-nums text-slate-900">{{ $rp($row['value']) }}</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ $row['percent'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    {{-- Rekonsiliasi kas --}}
    <x-ui.card title="Rekonsiliasi Kas" subtitle="Saldo awal, kas masuk/keluar, saldo akhir periode berjalan">
        @if ($rekon === null)
            <div class="flex h-24 items-center justify-center rounded-lg border border-dashed border-slate-200 text-sm text-slate-400">
                Rekonsiliasi tampil setelah periode dengan saldo awal terkunci.
            </div>
        @else
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex items-center justify-between py-2.5">
                    <dt class="text-slate-600">Saldo Awal Kas dan Bank</dt>
                    <dd class="font-medium tabular-nums text-slate-900">{{ $rp($rekon['saldo_awal']) }}</dd>
                </div>
                <div class="flex items-center justify-between py-2.5">
                    <dt class="text-slate-600">Total Kas Masuk</dt>
                    <dd class="font-medium tabular-nums text-emerald-700">+ {{ $rp($rekon['masuk']) }}</dd>
                </div>
                <div class="flex items-center justify-between py-2.5">
                    <dt class="text-slate-600">Total Kas Keluar</dt>
                    <dd class="font-medium tabular-nums text-rose-600">&minus; {{ $rp($rekon['keluar']) }}</dd>
                </div>
                <div class="flex items-center justify-between py-2.5">
                    <dt class="font-medium text-slate-700">Kenaikan Kas Bersih</dt>
                    <dd class="font-semibold tabular-nums text-slate-900">{{ $rp($rekon['kenaikan']) }}</dd>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-3">
                    <dt class="font-semibold text-slate-800">Saldo Akhir Kas dan Bank</dt>
                    <dd class="text-base font-bold tabular-nums text-blue-700">{{ $rp($rekon['saldo_akhir']) }}</dd>
                </div>
            </dl>
        @endif
    </x-ui.card>

    {{-- Ringkasan aktiva & pasiva --}}
    <x-ui.card title="Ringkasan Aktiva dan Pasiva" subtitle="Total Aktiva = Total Kewajiban + Total Modal">
        @if ($ringkasan === null)
            <div class="flex h-24 items-center justify-center rounded-lg border border-dashed border-slate-200 text-sm text-slate-400">
                Ringkasan tampil setelah periode dengan saldo awal terkunci.
            </div>
        @else
            @php($maks = max((float) $ringkasan['aktiva'], (float) $ringkasan['kewajiban'], (float) $ringkasan['modal'], 1))
            <div class="space-y-4">
                @foreach ([['Total Aktiva', $ringkasan['aktiva'], 'bg-blue-500'], ['Total Kewajiban', $ringkasan['kewajiban'], 'bg-rose-500'], ['Total Modal', $ringkasan['modal'], 'bg-emerald-500']] as [$label, $nilai, $bar])
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-700">{{ $label }}</span>
                            <span class="font-semibold tabular-nums text-slate-900">{{ $rp($nilai) }}</span>
                        </div>
                        <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full {{ $bar }}" style="width: {{ round((float) $nilai / $maks * 100, 1) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>

    {{-- Transaksi terbaru --}}
    <x-ui.card title="Transaksi Terbaru" subtitle="Lintas unit usaha">
        <x-slot:actions>
            <a href="{{ route('transactions.index') }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:underline">Lihat semua →</a>
        </x-slot:actions>

        @php($transaksi = $this->transaksiTerbaru())
        <x-ui.table :headers="['No. Transaksi', 'Tanggal', 'Unit Usaha', 'Jenis', 'Pihak', 'Total', 'Status', '']"
                    :is-empty="$transaksi->isEmpty()" empty="Belum ada transaksi.">
            @foreach ($transaksi as $trx)
                <tr class="hover:bg-slate-50/60">
                    <td class="px-4 py-2.5 font-medium text-slate-900">{{ $trx->nomor_transaksi }}</td>
                    <td class="px-4 py-2.5 tabular-nums text-slate-500">{{ $trx->tanggal?->format('d/m/Y') }}</td>
                    <td class="px-4 py-2.5 text-slate-600">{{ $trx->unitUsaha?->nama_unit ?? '—' }}</td>
                    <td class="px-4 py-2.5"><x-ui.badge color="slate">{{ ucfirst($trx->jenis_transaksi->value) }}</x-ui.badge></td>
                    <td class="px-4 py-2.5 text-slate-600">{{ $trx->pihak?->nama ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums font-medium text-slate-900">{{ $rp($trx->total) }}</td>
                    <td class="px-4 py-2.5">
                        @php($st = $trx->status->value)
                        <x-ui.badge :color="$st === 'posted' ? 'emerald' : ($st === 'reversed' ? 'amber' : 'slate')">{{ ucfirst($st) }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        <a href="{{ route('transactions.show', $trx) }}" wire:navigate class="text-sm font-medium text-emerald-700 hover:underline">Lihat</a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    {{-- Angsuran jatuh tempo --}}
    <x-ui.card title="Angsuran Jatuh Tempo" subtitle="Angsuran belum lunas, jatuh tempo terdekat">
        @php($angsuran = $this->angsuranJatuhTempo())
        <x-ui.table :headers="['No. Pinjaman', 'Peminjam', 'Angsuran ke', 'Jatuh Tempo', 'Pokok', 'Bunga', 'Total', 'Status']"
                    :is-empty="$angsuran->isEmpty()" empty="Tidak ada angsuran jatuh tempo.">
            @foreach ($angsuran as $a)
                @php($pinjaman = $a->pinjaman)
                @php($total = (float) $a->pokok_tagihan + (float) $a->bunga_tagihan + (float) $a->denda_tagihan)
                @php($menunggak = $a->tanggal_jatuh_tempo && $a->tanggal_jatuh_tempo->isPast())
                <tr class="hover:bg-slate-50/60">
                    <td class="px-4 py-2.5 font-medium text-slate-900">
                        @if ($pinjaman)
                            <a href="{{ route('loans.show', $pinjaman) }}" wire:navigate class="text-emerald-700 hover:underline">
                                {{ $pinjaman->transaksiPencairan?->nomor_transaksi ?? ('PJM-'.$pinjaman->id) }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-slate-600">{{ $pinjaman?->peminjam?->nama ?? '—' }}</td>
                    <td class="px-4 py-2.5 tabular-nums text-slate-600">{{ $a->angsuran_ke }}</td>
                    <td class="px-4 py-2.5 tabular-nums text-slate-500">{{ $a->tanggal_jatuh_tempo?->format('d/m/Y') }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-slate-600">{{ $rp($a->pokok_tagihan) }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-slate-600">{{ $rp($a->bunga_tagihan) }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums font-medium text-slate-900">{{ $rp((string) $total) }}</td>
                    <td class="px-4 py-2.5">
                        @if ($menunggak)
                            <x-ui.badge color="red">Menunggak</x-ui.badge>
                        @elseif ($a->status->value === 'sebagian')
                            <x-ui.badge color="amber">Sebagian</x-ui.badge>
                        @else
                            <x-ui.badge color="blue">Terjadwal</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    {{-- Stok menipis --}}
    <x-ui.card title="Stok Menipis" subtitle="Produk dengan stok di bawah atau sama dengan minimum">
        @php($produk = $this->stokMenipis())
        <x-ui.table :headers="['Produk', 'Unit Usaha', 'Stok Saat Ini', 'Stok Minimum', 'Status']"
                    :is-empty="$produk->isEmpty()" empty="Semua stok di atas minimum.">
            @foreach ($produk as $p)
                @php($kritis = (float) $p->stok_terkini <= 0 || (float) $p->stok_terkini <= (float) $p->stok_minimum * 0.5)
                <tr class="hover:bg-slate-50/60">
                    <td class="px-4 py-2.5">
                        <span class="font-medium text-slate-900">{{ $p->nama_produk }}</span>
                        <span class="block text-xs text-slate-400">{{ $p->kode_produk }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-slate-600">{{ $p->unitUsaha?->nama_unit ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums font-medium text-slate-900">{{ $qty($p->stok_terkini) }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-slate-500">{{ $qty($p->stok_minimum) }}</td>
                    <td class="px-4 py-2.5">
                        <x-ui.badge :color="$kritis ? 'red' : 'amber'">{{ $kritis ? 'Kritis' : 'Menipis' }}</x-ui.badge>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
