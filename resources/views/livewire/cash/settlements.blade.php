@php
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $isHutang = $mode === 'hutang';
    $today = now()->toDateString();
@endphp
<div class="space-y-6">
    <x-ui.breadcrumb :items="[['label' => 'Transaksi'], ['label' => $label]]" />

    <p class="text-sm text-slate-600">
        {{ $isHutang
            ? 'Bayar sekaligus beberapa nota pembelian kredit (praktik BUMDes: pembelian konsumsi dibayar borongan tiap tanggal 10/20/30). Jurnal: D Utang Usaha — K Kas/Bank.'
            : 'Terima pelunasan beberapa nota penjualan kredit sekaligus. Jurnal: D Kas/Bank — K Piutang Usaha.' }}
    </p>

    @error('form') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror

    <x-ui.card>
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">{{ $isHutang ? 'Pemasok' : 'Pelanggan' }}</label>
                    <select wire:model.live="pihakId" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— semua —</option>
                        @foreach ($pihakList as $p)
                            <option value="{{ $p->id }}">{{ $p->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.input label="Tanggal Bayar" type="date" wire:model="tanggal" :error="$errors->first('tanggal')" />
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">{{ $isHutang ? 'Dibayar dari' : 'Masuk ke' }}</label>
                    <select wire:model="akunKasId" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($akunKas as $a)
                            <option value="{{ $a->id }}">{{ $a->kode_akun }} — {{ $a->nama_akun }}</option>
                        @endforeach
                    </select>
                    @error('akunKasId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-ui.input label="Keterangan" wire:model="keterangan" placeholder="opsional" />
            </div>

            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="w-10 px-3 py-2 text-center">
                                <input type="checkbox" class="rounded border-slate-300 text-emerald-600" title="Pilih semua"
                                       @change="$wire.toggleAll($event.target.checked)">
                            </th>
                            <th class="px-3 py-2 text-left">No. Nota</th>
                            <th class="px-3 py-2 text-left">Tanggal</th>
                            <th class="px-3 py-2 text-left">Jatuh Tempo</th>
                            <th class="px-3 py-2 text-left">{{ $isHutang ? 'Pemasok' : 'Pelanggan' }}</th>
                            <th class="px-3 py-2 text-right">Total Nota</th>
                            <th class="px-3 py-2 text-right">Sisa</th>
                            <th class="w-40 px-3 py-2 text-right">Dibayar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            @php($k = $r['key'])
                            <tr wire:key="row-{{ $k }}" class="{{ ($pilih[$k] ?? false) ? 'bg-emerald-50/50' : '' }}">
                                <td class="px-3 py-2 text-center"><input type="checkbox" wire:model.live="pilih.{{ $k }}" class="rounded border-slate-300 text-emerald-600"></td>
                                <td class="px-3 py-2 font-medium">
                                    @if ($r['url'])
                                        <a href="{{ $r['url'] }}" wire:navigate class="text-emerald-700 hover:underline">{{ $r['nomor'] }}</a>
                                    @else
                                        <span class="text-slate-800">Nota {{ $r['nomor'] }}</span>
                                        <x-ui.badge color="slate">saldo awal</x-ui.badge>
                                        @if ($r['uraian'])<span class="block text-xs font-normal text-slate-500">{{ $r['uraian'] }}</span>@endif
                                    @endif
                                </td>
                                <td class="px-3 py-2 tabular-nums">{{ $r['tanggal']?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-3 py-2 tabular-nums {{ $r['jatuh_tempo'] && $r['jatuh_tempo']->toDateString() < $today ? 'font-medium text-red-600' : '' }}">{{ $r['jatuh_tempo']?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $r['pihak'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $rp($r['total']) }}</td>
                                <td class="px-3 py-2 text-right font-medium tabular-nums">{{ $rp($r['sisa']) }}</td>
                                <td class="px-3 py-1.5">
                                    <input type="number" step="any" min="0" wire:model.live.debounce.400ms="bayar.{{ $k }}" @disabled(! ($pilih[$k] ?? false))
                                           class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm tabular-nums disabled:bg-slate-50 disabled:text-slate-400">
                                    @error("bayar.{$k}") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-10 text-center text-slate-500">Tidak ada nota {{ $isHutang ? 'pembelian' : 'penjualan' }} kredit yang belum lunas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error('pilih') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">Total dibayar: <span class="text-lg font-semibold tabular-nums text-slate-900">{{ $rp($this->totalBayar()) }}</span></p>
                @can('perform-admin-mutation')
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $isHutang ? 'Bayar & Posting' : 'Terima & Posting' }}</x-ui.button>
                @endcan
            </div>
        </form>
    </x-ui.card>
</div>
