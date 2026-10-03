<div class="space-y-6">
    <x-ui.breadcrumb :items="[['label' => 'Stok Opname', 'url' => route('stocktakes.index')], ['label' => 'Baru']]" />

    @error('form') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror

    <x-ui.card title="Opname">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Unit Usaha</label>
                <select wire:model.live="unit_usaha_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— pilih —</option>
                    @foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->nama_unit }}</option>@endforeach
                </select>
                @error('unit_usaha_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <x-ui.input label="Tanggal" type="date" wire:model="tanggal" :error="$errors->first('tanggal')" />
        </div>
    </x-ui.card>

    <x-ui.card title="Hitung Fisik">
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Produk</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Stok Fisik</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($items as $i => $item)
                        <tr wire:key="opn-item-{{ $i }}">
                            <td class="px-3 py-1.5">
                                <select wire:model="items.{{ $i }}.produk_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <option value="">— produk —</option>
                                    @foreach ($produk as $pr)
                                        <option value="{{ $pr->id }}">{{ $pr->kode_produk }} — {{ $pr->nama_produk }} (sistem {{ rtrim(rtrim($pr->stok_terkini, '0'), '.') }})</option>
                                    @endforeach
                                </select>
                                @error("items.$i.produk_id") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-3 py-1.5"><input type="number" step="0.0001" wire:model="items.{{ $i }}.stok_fisik" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                            <td class="px-3 py-1.5 text-right">
                                @if (count($items) > 1)<button wire:click="removeItem({{ $i }})" class="text-sm text-red-600 hover:underline">Hapus</button>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('items') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        <div class="mt-3 flex justify-between">
            <x-ui.button variant="secondary" wire:click="addItem">+ Baris</x-ui.button>
            <x-ui.button wire:click="save">Simpan Draft Opname</x-ui.button>
        </div>
    </x-ui.card>
</div>
