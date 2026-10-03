<div class="space-y-6">
    <x-ui.breadcrumb :items="[
        ['label' => 'Transaksi'],
        ['label' => ucfirst($jenis).' baru'],
    ]" />

    @error('form') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror

    <x-ui.card title="Informasi {{ ucfirst($jenis) }}">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-ui.input label="Tanggal" type="date" wire:model="tanggal" :error="$errors->first('tanggal')" />
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Unit Usaha</label>
                <select wire:model="unit_usaha_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— pilih —</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}">{{ $u->nama_unit }}</option>
                    @endforeach
                </select>
                @error('unit_usaha_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">{{ $jenis === 'penjualan' ? 'Pelanggan' : 'Pemasok' }}</label>
                <select wire:model="pihak_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— opsional / umum —</option>
                    @foreach ($pihak as $p)
                        <option value="{{ $p->id }}">{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Metode</label>
                <select wire:model.live="metode_pembayaran" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="tunai">Tunai</option>
                    <option value="kredit">Kredit</option>
                </select>
            </div>
            @if ($metode_pembayaran === 'tunai')
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">Akun Kas/Bank</label>
                    <select wire:model="akun_kas_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— pilih —</option>
                        @foreach ($akunKas as $a)
                            <option value="{{ $a->id }}">{{ $a->kode_akun }} — {{ $a->nama_akun }}</option>
                        @endforeach
                    </select>
                    @error('akun_kas_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @else
                <x-ui.input label="Jatuh Tempo" type="date" wire:model="jatuh_tempo" :error="$errors->first('jatuh_tempo')" />
            @endif
            <x-ui.input label="Keterangan" wire:model="keterangan" />
        </div>
    </x-ui.card>

    <x-ui.card title="Rincian Barang">
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Produk</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Qty</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Harga</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Diskon</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Pajak</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($items as $i => $item)
                        <tr wire:key="item-{{ $i }}">
                            <td class="px-3 py-1.5">
                                <select wire:model="items.{{ $i }}.produk_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <option value="">— produk —</option>
                                    @foreach ($produk as $pr)
                                        <option value="{{ $pr->id }}">{{ $pr->kode_produk }} — {{ $pr->nama_produk }}</option>
                                    @endforeach
                                </select>
                                @error("items.$i.produk_id") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-3 py-1.5"><input type="number" step="0.0001" min="0" wire:model.live.debounce.500ms="items.{{ $i }}.kuantitas" class="w-full min-w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm tabular-nums"></td>
                            <td class="px-3 py-1.5"><input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="items.{{ $i }}.harga" class="w-full min-w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm tabular-nums"></td>
                            <td class="px-3 py-1.5">
                                <input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="items.{{ $i }}.diskon" class="w-full min-w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm tabular-nums">
                                @error("items.$i.diskon") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-3 py-1.5"><input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="items.{{ $i }}.pajak" class="w-full min-w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm tabular-nums"></td>
                            <td class="px-3 py-1.5 text-right">
                                @if (count($items) > 1)
                                    <button wire:click="removeItem({{ $i }})" class="text-sm text-red-600 hover:underline">Hapus</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('items') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

        @php($totalNegatif = (float) $this->total < 0)
        <div class="mt-3 flex items-center justify-between">
            <x-ui.button variant="secondary" wire:click="addItem">+ Baris</x-ui.button>
            <div class="text-sm text-slate-600">Total:
                <span @class(['font-semibold tabular-nums', 'text-red-600' => $totalNegatif, 'text-slate-900' => ! $totalNegatif])>{{ number_format((float) $this->total, 2, ',', '.') }}</span>
            </div>
        </div>
        @if ($totalNegatif)
            <p class="mt-1 text-right text-xs text-red-600">Total negatif — periksa kolom Harga/Diskon (diskon tidak boleh melebihi qty × harga).</p>
        @endif

        <div class="mt-4 flex justify-end">
            <x-ui.button wire:click="save" wire:loading.attr="disabled" :disabled="$totalNegatif">Simpan Draft</x-ui.button>
        </div>
    </x-ui.card>
</div>
