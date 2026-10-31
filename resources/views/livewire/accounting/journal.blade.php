@php
    $rp = fn ($v) => (float) $v ? 'Rp '.number_format((float) $v, 0, ',', '.') : '';
    $exportParams = ['type' => 'jurnal-umum', 'from' => $from, 'to' => $to, 'unitId' => $unitId];
@endphp
<div class="space-y-4">
    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
        <strong class="text-slate-800">Jurnal Umum</strong> = hasil pencatatan double-entry (debet–kredit) dari dokumen yang sudah <em>diposting</em>.
        Bersifat baca-saja; bila ada yang salah, buka dokumennya di <a href="{{ route('transactions.index') }}" wire:navigate class="font-medium text-emerald-700 underline">Daftar Transaksi</a>
        lalu <em>Batal Posting</em> → edit → posting ulang. Draft belum masuk jurnal.
    </div>

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-end gap-2">
            <select wire:model.live="bulan" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach ($bulanList as $n => $nama)
                    <option value="{{ $n }}">{{ $nama }}</option>
                @endforeach
            </select>
            <select wire:model.live="tahun" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach ($tahunList as $th)
                    <option value="{{ $th }}">{{ $th }}</option>
                @endforeach
            </select>
            <select wire:model.live="unitId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua unit</option>
                @foreach ($units as $u)
                    <option value="{{ $u->id }}">{{ $u->nama_unit }}</option>
                @endforeach
            </select>
            <select wire:model.live="jenis" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua jenis</option>
                @foreach ($jenisList as $j)
                    <option value="{{ $j->value }}">{{ ucfirst($j->value) }}</option>
                @endforeach
            </select>
            <div class="w-56"><x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari bukti / akun / uraian…" /></div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('reports.export', $exportParams + ['format' => 'pdf']) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Unduh PDF</a>
            <a href="{{ route('reports.export', $exportParams + ['format' => 'xlsx']) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Unduh Excel</a>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        @if ($jurnal['seimbang'])
            <x-ui.badge color="emerald">Seimbang — total debet = total kredit</x-ui.badge>
        @else
            <x-ui.badge color="red">TIDAK SEIMBANG — periksa jurnal</x-ui.badge>
        @endif
        <span class="text-sm text-slate-500">{{ $groups->count() }} bukti · {{ count($jurnal['entries']) }} baris</span>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-3 py-2.5 text-left">Tanggal</th>
                    <th class="px-3 py-2.5 text-left">No. Bukti</th>
                    <th class="px-3 py-2.5 text-left">Akun</th>
                    <th class="px-3 py-2.5 text-left">Uraian</th>
                    <th class="px-3 py-2.5 text-right">Debet</th>
                    <th class="px-3 py-2.5 text-right">Kredit</th>
                </tr>
            </thead>
            @forelse ($groups as $tid => $lines)
                <tbody class="border-t border-slate-200" wire:key="j-{{ $tid }}">
                    @foreach ($lines as $e)
                        <tr class="{{ $loop->first ? '' : 'border-t border-slate-50' }}">
                            <td class="px-3 py-1.5 whitespace-nowrap tabular-nums text-slate-500">{{ $loop->first ? \Illuminate\Support\Carbon::parse($e['tanggal'])->format('d/m/Y') : '' }}</td>
                            <td class="px-3 py-1.5 whitespace-nowrap">
                                @if ($loop->first)
                                    <a href="{{ route('transactions.show', $tid) }}" wire:navigate class="font-medium text-emerald-700 hover:underline">{{ $e['nomor'] }}</a>
                                    <span class="block text-[11px] capitalize text-slate-400">{{ $e['jenis'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 {{ (float) $e['kredit'] ? 'pl-10' : '' }}">{{ $e['akun'] }}</td>
                            <td class="px-3 py-1.5 text-slate-500">{{ $e['uraian'] }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums whitespace-nowrap">{{ $rp($e['debit']) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums whitespace-nowrap">{{ $rp($e['kredit']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            @empty
                <tbody><tr><td colspan="6" class="px-3 py-10 text-center text-slate-500">Belum ada jurnal terposting pada periode ini.</td></tr></tbody>
            @endforelse
            <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                <tr>
                    <td colspan="4" class="px-3 py-2.5 text-right">TOTAL</td>
                    <td class="px-3 py-2.5 text-right tabular-nums">Rp {{ number_format((float) $jurnal['total_debit'], 0, ',', '.') }}</td>
                    <td class="px-3 py-2.5 text-right tabular-nums">Rp {{ number_format((float) $jurnal['total_kredit'], 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
