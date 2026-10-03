<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">Selamat datang, {{ auth()->user()->nama }}.</p>
            <p class="text-xs text-slate-400">Angka dihitung dari jurnal terposting dan saldo awal terkunci.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('reports.index') }}" wire:navigate>
                <x-ui.button variant="secondary">Laporan</x-ui.button>
            </a>
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
        <x-ui.card title="Pendapatan vs Beban" subtitle="Periode berjalan (akumulatif)">
            @php($r = $this->ringkasan())
            @if ($r === null)
                <div class="flex h-48 items-center justify-center rounded-lg border border-dashed border-slate-200 text-sm text-slate-400">
                    Ringkasan tampil setelah ada transaksi terposting.
                </div>
            @else
                @php($pendapatan = (float) $r['penjualan_bersih'] + (float) $r['pendapatan_kredit'] + (float) $r['pendapatan_lain'])
                @php($beban = (float) $r['hpp'] + (float) $r['beban_operasional'] + (float) $r['penyusutan'] + (float) $r['beban_selisih_persediaan'])
                @php($maks = max($pendapatan, $beban, 1))
                <div class="space-y-4">
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600">Pendapatan</span>
                            <span class="font-semibold tabular-nums text-emerald-700">Rp {{ number_format($pendapatan, 2, ',', '.') }}</span>
                        </div>
                        <div class="mt-1 h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ round($pendapatan / $maks * 100, 1) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600">Beban</span>
                            <span class="font-semibold tabular-nums text-rose-700">Rp {{ number_format($beban, 2, ',', '.') }}</span>
                        </div>
                        <div class="mt-1 h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-rose-500" style="width: {{ round($beban / $maks * 100, 1) }}%"></div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-3 text-sm">
                        <span class="font-medium text-slate-700">Laba Bersih</span>
                        <span class="font-semibold tabular-nums text-slate-900">Rp {{ number_format((float) $r['laba_bersih'], 2, ',', '.') }}</span>
                    </div>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Antrean Persetujuan Pimpinan" subtitle="Stok opname menunggu persetujuan">
            @php($antrean = $this->antrean())
            <x-ui.table :headers="['Opname', 'Unit', 'Tanggal']" :is-empty="$antrean->isEmpty()" empty="Tidak ada antrean approval.">
                @foreach ($antrean as $item)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">
                            <a href="{{ route('stocktakes.show', $item) }}" wire:navigate class="text-indigo-600 hover:underline">
                                {{ $item->nomor_opname ?? ('Opname #'.$item->id) }}
                            </a>
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $item->unitUsaha?->nama_unit ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-500 tabular-nums">{{ $item->tanggal?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    </div>
</div>
