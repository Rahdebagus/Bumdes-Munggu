<div class="space-y-6">
    <x-ui.breadcrumb :items="[['label' => 'Kas'], ['label' => 'Transaksi baru']]" />

    @error('form') <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div> @enderror

    <x-ui.card title="Transaksi Kas">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-ui.input label="Tanggal" type="date" wire:model="tanggal" :error="$errors->first('tanggal')" />
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Kategori</label>
                <select wire:model.live="kategori_kode" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($categories as $kode => $k)
                        <option value="{{ $kode }}">{{ $k['label'] }} ({{ $k['arah'] }})</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Unit Usaha</label>
                <select wire:model="unit_usaha_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— opsional —</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}">{{ $u->nama_unit }}</option>
                    @endforeach
                </select>
            </div>
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
            <x-ui.input label="Keterangan" wire:model="keterangan" />
        </div>
    </x-ui.card>

    @unless ($isPelunasan)
        <x-ui.card title="Nominal & Akun Lawan">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-slate-700">Akun Lawan</label>
                    <select wire:model="akun_lawan_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— pilih —</option>
                        @foreach ($lawanOptions as $a)
                            <option value="{{ $a->id }}">{{ $a->kode_akun }} — {{ $a->nama_akun }}</option>
                        @endforeach
                    </select>
                    @error('akun_lawan_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-ui.input label="Nominal" type="number" step="0.01" wire:model="nominal" :error="$errors->first('nominal')" />
            </div>
        </x-ui.card>
    @else
        <x-ui.card title="Alokasi Pelunasan" subtitle="Pilih invoice yang dilunasi (tidak boleh melebihi sisa)">
            @if ($outstanding->isEmpty())
                <p class="text-sm text-slate-500">Tidak ada invoice kredit dengan sisa untuk dilunasi.</p>
            @else
                <div class="space-y-2">
                    @foreach ($allocations as $i => $alloc)
                        <div class="flex items-center gap-2" wire:key="alloc-{{ $i }}">
                            <select wire:model="allocations.{{ $i }}.invoice_transaksi_id" class="flex-1 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <option value="">— invoice —</option>
                                @foreach ($outstanding as $row)
                                    <option value="{{ $row->trx->id }}">{{ $row->trx->nomor_transaksi }} (sisa {{ number_format((float) $row->sisa, 2, ',', '.') }})</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" wire:model="allocations.{{ $i }}.nominal" placeholder="nominal" class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm">
                            @if (count($allocations) > 1)
                                <button wire:click="removeAllocation({{ $i }})" class="text-sm text-red-600 hover:underline">Hapus</button>
                            @endif
                        </div>
                        @error("allocations.$i.invoice_transaksi_id") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        @error("allocations.$i.nominal") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @endforeach
                </div>
                <div class="mt-3"><x-ui.button variant="secondary" wire:click="addAllocation">+ Alokasi</x-ui.button></div>
            @endif
        </x-ui.card>
    @endunless

    <div class="flex justify-end">
        <x-ui.button wire:click="save" wire:loading.attr="disabled">Simpan Draft</x-ui.button>
    </div>
</div>
