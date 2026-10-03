<div class="space-y-6">
    <x-ui.breadcrumb :items="[['label' => 'Aset Tetap', 'url' => route('assets.index')], ['label' => 'Daftar']]" />

    <x-ui.card title="Register Aset Tetap" subtitle="Residu ≤ perolehan; penyusutan garis lurus">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-ui.input label="Kode Aset" wire:model="kode_aset" :error="$errors->first('kode_aset')" />
            <x-ui.input label="Nama Aset" wire:model="nama_aset" :error="$errors->first('nama_aset')" />
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Unit Usaha</label>
                <select wire:model="unit_usaha_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— opsional —</option>
                    @foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->nama_unit }}</option>@endforeach
                </select>
            </div>
            <x-ui.input label="Tanggal Perolehan" type="date" wire:model="tanggal_perolehan" :error="$errors->first('tanggal_perolehan')" />
            <x-ui.input label="Harga Perolehan" type="number" step="0.01" wire:model="harga_perolehan" :error="$errors->first('harga_perolehan')" />
            <x-ui.input label="Nilai Residu" type="number" step="0.01" wire:model="nilai_residu" :error="$errors->first('nilai_residu')" />
            <x-ui.input label="Umur (bulan)" type="number" wire:model="umur_manfaat" :error="$errors->first('umur_manfaat')" />
            <x-ui.input label="Mulai Penyusutan" type="date" wire:model="tanggal_mulai_susut" :error="$errors->first('tanggal_mulai_susut')" />
            <x-ui.input label="Akumulasi Awal (migrasi)" type="number" step="0.01" wire:model="akumulasi_awal" :error="$errors->first('akumulasi_awal')" />
        </div>
        <div class="mt-4 flex justify-end">
            <x-ui.button wire:click="save">Simpan Aset</x-ui.button>
        </div>
    </x-ui.card>
</div>
