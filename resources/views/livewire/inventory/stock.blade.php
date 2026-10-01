@php
    $fmtQty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
    $fmtMoney = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<div class="space-y-6">
    <div class="w-72">
        <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari produk…" />
    </div>

    <x-ui.card title="Stok Barang" subtitle="Stok & HPP rata-rata dikelola sistem (mutasi stok)">
        <x-ui.table :headers="['Kode', 'Nama', 'Unit', 'Stok', 'HPP rata', 'Nilai', '']"
                    :is-empty="$produk->isEmpty()" empty="Belum ada produk.">
            @foreach ($produk as $p)
                <tr wire:key="stk-{{ $p->id }}">
                    <td class="px-4 py-2 font-medium text-slate-900">{{ $p->kode_produk }}</td>
                    <td class="px-4 py-2">{{ $p->nama_produk }}</td>
                    <td class="px-4 py-2">{{ $p->unitUsaha?->nama_unit }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $fmtQty($p->stok_terkini) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ $fmtMoney($p->harga_pokok_rata) }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $fmtMoney(bcmul((string) $p->stok_terkini, (string) $p->harga_pokok_rata, 2)) }}</td>
                    <td class="px-4 py-2 text-right">
                        <button wire:click="showCard({{ $p->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Kartu stok</button>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-4">{{ $produk->links() }}</div>
    </x-ui.card>

    @if ($kartuProduk)
        <x-ui.card title="Kartu Stok — {{ $kartuProduk->nama_produk }}" subtitle="Urut mutasi">
            <x-ui.table :headers="['#', 'Tanggal', 'Jenis', 'Masuk', 'Keluar', 'HPP', 'Saldo Qty', 'Saldo Nilai']"
                        :is-empty="$mutasi->isEmpty()" empty="Belum ada mutasi.">
                @foreach ($mutasi as $m)
                    <tr wire:key="mut-{{ $m->id }}">
                        <td class="px-4 py-2">{{ $m->urutan }}</td>
                        <td class="px-4 py-2">{{ $m->tanggal->format('d M Y') }}</td>
                        <td class="px-4 py-2 capitalize">{{ $m->jenis->value }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ (float) $m->kuantitas_masuk ? $fmtQty($m->kuantitas_masuk) : '' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ (float) $m->kuantitas_keluar ? $fmtQty($m->kuantitas_keluar) : '' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ $fmtMoney($m->nilai_masuk ?: $m->nilai_keluar) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $fmtQty($m->saldo_kuantitas) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $fmtMoney($m->saldo_nilai) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif
</div>
