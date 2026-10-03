<div class="space-y-6">
    <x-ui.breadcrumb :items="[['label' => 'Aset Tetap', 'url' => route('assets.index')], ['label' => $aset->kode_aset]]" />

    @error('dep') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4 text-sm">
            <div class="space-y-1">
                <p class="text-lg font-semibold text-slate-900">{{ $aset->kode_aset }} — {{ $aset->nama_aset }}</p>
                <p class="text-slate-500">Perolehan {{ $aset->tanggal_perolehan->format('d M Y') }} · Umur {{ $aset->umur_manfaat }} bln · Residu {{ number_format((float) $aset->nilai_residu, 2, ',', '.') }}</p>
                <p class="text-slate-500">Mulai susut {{ optional($aset->tanggal_mulai_susut)->format('d M Y') }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400">Harga Perolehan / Akumulasi / Nilai Buku</p>
                <p class="text-base font-semibold tabular-nums text-slate-900">
                    {{ number_format((float) $aset->harga_perolehan, 2, ',', '.') }}
                    / {{ number_format((float) $akumulasi, 2, ',', '.') }}
                    / {{ number_format((float) $nilaiBuku, 2, ',', '.') }}
                </p>
            </div>
        </div>
    </x-ui.card>

    @can('perform-admin-mutation')
        <x-ui.card title="Posting Penyusutan" subtitle="Satu transaksi efektif per aset + periode">
            <div class="flex flex-wrap items-end gap-3">
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">Periode</label>
                    <select wire:model="depPeriodeId" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— pilih periode —</option>
                        @foreach ($periods as $p)
                            <option value="{{ $p->id }}">{{ str_pad($p->bulan, 2, '0', STR_PAD_LEFT) }}/{{ $p->tahun }}</option>
                        @endforeach
                    </select>
                    @error('depPeriodeId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-ui.button wire:click="postDepreciation">Posting Penyusutan</x-ui.button>
            </div>
        </x-ui.card>
    @endcan

    <x-ui.card title="Riwayat Penyusutan">
        <x-ui.table :headers="['Nomor', 'Tanggal', 'Nominal']" :is-empty="$postedDep->isEmpty()" empty="Belum ada penyusutan diposting.">
            @foreach ($postedDep as $t)
                <tr wire:key="dep-{{ $t->id }}">
                    <td class="px-4 py-2">
                        <a href="{{ route('transactions.show', $t) }}" wire:navigate class="text-emerald-700 hover:underline">{{ $t->nomor_transaksi }}</a>
                    </td>
                    <td class="px-4 py-2">{{ $t->tanggal->format('d M Y') }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $t->total, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>
</div>
