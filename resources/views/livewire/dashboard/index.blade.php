<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">Selamat datang, {{ auth()->user()->nama }}.</p>
            <p class="text-xs text-slate-400">Data akan tampil setelah modul transaksi dibangun (tahap berikutnya).</p>
        </div>
        <div class="flex items-center gap-2">
            <x-ui.badge color="amber">Periode belum dibuka</x-ui.badge>
            @can('perform-admin-mutation')
                <x-ui.button variant="secondary" wire:click="clearReportCache" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="clearReportCache">Bersihkan cache</span>
                    <span wire:loading wire:target="clearReportCache">Memproses…</span>
                </x-ui.button>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->kpis() as $kpi)
            <x-ui.card>
                <p class="text-sm text-slate-500">{{ $kpi['label'] }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ $kpi['value'] }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $kpi['hint'] }}</p>
            </x-ui.card>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-ui.card title="Tren Pendapatan vs Beban" subtitle="Belum ada data">
            <div class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 text-sm text-slate-400">
                Grafik tampil setelah ada transaksi terposting.
            </div>
        </x-ui.card>

        <x-ui.card title="Antrean Persetujuan Pimpinan" subtitle="Belum ada item">
            <x-ui.table :headers="['Item', 'Jenis', 'Tanggal']" :is-empty="true" empty="Tidak ada antrean approval." />
        </x-ui.card>
    </div>
</div>
