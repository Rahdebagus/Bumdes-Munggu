@php use App\Enums\StatusUmum; @endphp
<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="w-72">
            <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari kode / nama produk…" />
        </div>
        @can('perform-admin-mutation')
            <x-ui.button wire:click="create">+ Tambah Produk</x-ui.button>
        @endcan
    </div>

    <x-ui.table :headers="['Kode', 'Nama', 'Unit', 'Satuan', 'Harga Jual', 'Stok (cache)', 'Status', '']"
                :is-empty="$produk->isEmpty()" empty="Belum ada produk.">
        @foreach ($produk as $p)
            <tr wire:key="produk-{{ $p->id }}">
                <td class="px-4 py-2 font-medium text-slate-900">{{ $p->kode_produk }}</td>
                <td class="px-4 py-2">{{ $p->nama_produk }}</td>
                <td class="px-4 py-2">{{ $p->unitUsaha?->nama_unit }}</td>
                <td class="px-4 py-2">{{ $p->satuan }}</td>
                <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $p->harga_jual, 2, ',', '.') }}</td>
                <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ rtrim(rtrim($p->stok_terkini, '0'), '.') }}</td>
                <td class="px-4 py-2">
                    <x-ui.badge :color="$p->status === StatusUmum::Aktif ? 'emerald' : 'slate'">{{ $p->status->value }}</x-ui.badge>
                </td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    @can('perform-admin-mutation')
                        <button wire:click="edit({{ $p->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Edit</button>
                        <button wire:click="toggleStatus({{ $p->id }})" class="ml-3 text-sm text-slate-500 hover:underline">
                            {{ $p->status === StatusUmum::Aktif ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-ui.table>

    <div class="mt-4">{{ $produk->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="$set('showModal', false)"></div>
            <div class="relative w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $form->produkId ? 'Edit' : 'Tambah' }} Produk</h2>
                </div>
                <form wire:submit="save" class="space-y-3 px-5 py-4">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700">Unit Usaha</label>
                        <select wire:model="form.unit_usaha_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— pilih unit —</option>
                            @foreach ($units as $u)
                                <option value="{{ $u->id }}">{{ $u->nama_unit }}</option>
                            @endforeach
                        </select>
                        @error('form.unit_usaha_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.input label="Kode Produk" wire:model="form.kode_produk" :error="$errors->first('form.kode_produk')" />
                        <x-ui.input label="Satuan" wire:model="form.satuan" :error="$errors->first('form.satuan')" />
                    </div>
                    <x-ui.input label="Nama Produk" wire:model="form.nama_produk" :error="$errors->first('form.nama_produk')" />
                    <div class="grid grid-cols-3 gap-3">
                        <x-ui.input label="Harga Beli" type="number" step="0.01" wire:model="form.harga_beli" :error="$errors->first('form.harga_beli')" />
                        <x-ui.input label="Harga Jual" type="number" step="0.01" wire:model="form.harga_jual" :error="$errors->first('form.harga_jual')" />
                        <x-ui.input label="Stok Minimum" type="number" step="0.0001" wire:model="form.stok_minimum" :error="$errors->first('form.stok_minimum')" />
                    </div>
                    <p class="text-xs text-slate-400">Stok berjalan &amp; HPP rata-rata dikelola sistem (mutasi stok), tidak diedit di sini.</p>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700">Status</label>
                        <select wire:model="form.status" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Batal</x-ui.button>
                        <x-ui.button type="submit">Simpan</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
