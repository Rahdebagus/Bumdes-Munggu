<div class="space-y-6">
    <x-ui.breadcrumb :items="[['label' => 'Pinjaman', 'url' => route('loans.index')], ['label' => 'Buat']]" />

    <x-ui.card title="Kontrak Pinjaman">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Unit Usaha (kredit)</label>
                <select wire:model="unit_usaha_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— pilih —</option>
                    @foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->nama_unit }}</option>@endforeach
                </select>
                @error('unit_usaha_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Peminjam</label>
                <select wire:model="peminjam_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— pilih —</option>
                    @foreach ($peminjam as $p)<option value="{{ $p->id }}">{{ $p->nama }}</option>@endforeach
                </select>
                @error('peminjam_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <x-ui.input label="Tanggal Pinjaman" type="date" wire:model="tanggal_pinjaman" :error="$errors->first('tanggal_pinjaman')" />
            <x-ui.input label="Pokok" type="number" step="0.01" wire:model.live.debounce.400ms="pokok_pinjaman" :error="$errors->first('pokok_pinjaman')" />
            <x-ui.input label="Bunga (%)" type="number" step="0.01" wire:model.live.debounce.400ms="persentase_bunga" :error="$errors->first('persentase_bunga')" />
            <x-ui.input label="Tenor (bulan)" type="number" wire:model.live.debounce.400ms="tenor" :error="$errors->first('tenor')" />
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Basis Bunga</label>
                <select wire:model.live="basis_bunga" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="flat_total">Flat Total (pokok × %)</option>
                    <option value="flat_bulanan">Flat Bulanan (pokok × % × tenor)</option>
                </select>
            </div>
            <x-ui.input label="Angsuran Pertama" type="date" wire:model.live="tanggal_angsuran_pertama" :error="$errors->first('tanggal_angsuran_pertama')" />
        </div>
    </x-ui.card>

    @if ($previewData)
        <x-ui.card title="Pratinjau Jadwal" subtitle="Total bunga: {{ number_format((float) $previewData['total_bunga'], 2, ',', '.') }}">
            <x-ui.table :headers="['Ke', 'Jatuh Tempo', 'Pokok', 'Bunga']">
                @foreach ($previewData['jadwal'] as $row)
                    <tr>
                        <td class="px-4 py-2">{{ $row['angsuran_ke'] }}</td>
                        <td class="px-4 py-2">{{ $row['tanggal_jatuh_tempo'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $row['pokok_tagihan'], 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $row['bunga_tagihan'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    @endif

    <div class="flex justify-end">
        <x-ui.button wire:click="save" wire:loading.attr="disabled">Simpan Draft Pinjaman</x-ui.button>
    </div>
</div>
