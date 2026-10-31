@php use App\Enums\StatusUmum; @endphp
<div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="w-64">
                <x-ui.input wire:model.live.debounce.300ms="q" placeholder="Cari kode / nama produk…" />
            </div>
            <select wire:model.live="kategori" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua kategori</option>
                @foreach ($kategoriList as $k)
                    <option value="{{ $k->value }}">{{ $k->label() }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" wire:model.live="perluVerifikasi" class="rounded border-slate-300 text-amber-600">
                Perlu verifikasi harga
                @if ($jumlahVerifikasi > 0)
                    <x-ui.badge color="amber">{{ $jumlahVerifikasi }}</x-ui.badge>
                @endif
            </label>
        </div>
        @can('perform-admin-mutation')
            <div class="flex gap-2">
                <x-ui.button variant="secondary" wire:click="$toggle('showImport')">Impor CSV/Excel</x-ui.button>
                <x-ui.button wire:click="create">+ Tambah Produk</x-ui.button>
            </div>
        @endcan
    </div>

    @if ($showImport)
        <x-ui.card class="mb-4" title="Impor / perbarui produk" subtitle="Kolom: nama, satuan, kategori, stok, harga_beli, harga_jual, sumber_harga_jual. Upsert berdasarkan nama — harga produk yang sudah ada diperbarui.">
            <form wire:submit="import" class="flex flex-wrap items-center gap-3">
                <input type="file" wire:model="importFile" accept=".csv,.xlsx,.xls" class="text-sm">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="import,importFile">Impor</x-ui.button>
                <span wire:loading wire:target="importFile" class="text-xs text-slate-500">mengunggah…</span>
            </form>
            @error('importFile') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-slate-500">Kolom stok tidak diterapkan dari sini. Stok awal diposting lewat perintah
                <code class="rounded bg-slate-100 px-1">php artisan produk:import file.csv --stok-awal=YYYY-MM-DD</code>; selisih stok berikutnya lewat Stok Opname.</p>
            @if ($importReport)
                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
                    <p>{{ $importReport['dibuat'] }} produk baru, {{ $importReport['diperbarui'] }} diperbarui.</p>
                    @foreach ($importReport['galat'] as $g)
                        <p class="text-amber-700">{{ $g }}</p>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.table :headers="['Kode', 'Nama', 'Kategori', 'Satuan', 'Harga Beli', 'Harga Jual', 'Stok', 'Status', '']"
                :is-empty="$produk->isEmpty()" empty="Belum ada produk.">
        @foreach ($produk as $p)
            <tr wire:key="produk-{{ $p->id }}">
                <td class="px-4 py-2 font-medium whitespace-nowrap text-slate-900">{{ $p->kode_produk }}</td>
                <td class="px-4 py-2">
                    {{ $p->nama_produk }}
                    <span class="block text-xs text-slate-400">{{ $p->unitUsaha?->nama_unit }}</span>
                </td>
                <td class="px-4 py-2 text-sm">{{ $p->kategori?->label() ?? '—' }}</td>
                <td class="px-4 py-2">{{ $p->satuan }}</td>
                @foreach (['harga_beli', 'harga_jual'] as $field)
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        @can('perform-admin-mutation')
                            {{-- Edit inline: klik angka → ketik → Enter/keluar untuk simpan, Esc batal. --}}
                            <div x-data="{ editing: false, val: '{{ (float) $p->{$field} }}' }" class="inline-block">
                                <button type="button" x-show="!editing" @click="editing = true; $nextTick(() => $refs.inp.select())"
                                        class="tabular-nums hover:text-emerald-700 hover:underline" title="Klik untuk ubah">{{ 'Rp '.number_format((float) $p->{$field}, 0, ',', '.') }}</button>
                                <input x-ref="inp" x-show="editing" x-cloak x-model="val" type="number" min="0" step="any"
                                       @keydown.enter.prevent="editing = false; $wire.updatePrice({{ $p->id }}, '{{ $field }}', val)"
                                       @keydown.escape="editing = false; val = '{{ (float) $p->{$field} }}'"
                                       @blur="if (editing) { editing = false; $wire.updatePrice({{ $p->id }}, '{{ $field }}', val) }"
                                       class="w-28 rounded border border-emerald-400 px-2 py-1 text-right text-sm tabular-nums">
                            </div>
                        @else
                            <span class="tabular-nums">{{ 'Rp '.number_format((float) $p->{$field}, 0, ',', '.') }}</span>
                        @endcan
                        @if ($field === 'harga_jual' && $p->harga_jual_perlu_verifikasi)
                            <span class="mt-0.5 block">
                                <button type="button" @can('perform-admin-mutation') wire:click="markVerified({{ $p->id }})" @endcan
                                        title="{{ $p->sumber_harga_jual }} — klik bila harga sudah benar">
                                    <x-ui.badge color="amber">perlu verifikasi</x-ui.badge>
                                </button>
                            </span>
                        @endif
                    </td>
                @endforeach
                <td class="px-4 py-2 text-right tabular-nums text-slate-500">
                    @if ($p->lacak_stok)
                        {{ rtrim(rtrim($p->stok_terkini, '0'), '.') ?: '0' }}
                    @else
                        <span class="text-xs">jasa</span>
                    @endif
                </td>
                <td class="px-4 py-2">
                    <x-ui.badge :color="$p->status === StatusUmum::Aktif ? 'emerald' : 'slate'">{{ $p->status->value }}</x-ui.badge>
                </td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    @can('perform-admin-mutation')
                        <button type="button" wire:click="edit({{ $p->id }})" class="text-sm font-medium text-emerald-700 hover:underline">Edit</button>
                        <button type="button" wire:click="toggleStatus({{ $p->id }})" class="ml-3 text-sm text-slate-500 hover:underline">
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
                    <x-ui.input label="Nama Produk" wire:model="form.nama_produk" :error="$errors->first('form.nama_produk')" />
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-slate-700">Kategori</label>
                            <select wire:model.live="form.kategori" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">— pilih —</option>
                                @foreach ($kategoriList as $k)
                                    <option value="{{ $k->value }}">{{ $k->label() }}</option>
                                @endforeach
                            </select>
                            @error('form.kategori') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <x-ui.input label="Satuan" wire:model="form.satuan" :error="$errors->first('form.satuan')" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-slate-700">Unit Usaha</label>
                            <select wire:model="form.unit_usaha_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="">{{ $form->produkId ? '— pilih unit —' : 'Otomatis dari kategori' }}</option>
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->nama_unit }}</option>
                                @endforeach
                            </select>
                            @error('form.unit_usaha_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <x-ui.input label="Kode Produk" wire:model="form.kode_produk" :placeholder="$form->produkId ? '' : 'Otomatis (mis. KSM-001)'" :error="$errors->first('form.kode_produk')" />
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <x-ui.input label="Harga Beli" type="number" step="any" min="0" wire:model="form.harga_beli" :error="$errors->first('form.harga_beli')" />
                        <x-ui.input label="Harga Jual" type="number" step="any" min="0" wire:model="form.harga_jual" :error="$errors->first('form.harga_jual')" />
                        <x-ui.input label="Stok Minimum" type="number" step="any" min="0" wire:model="form.stok_minimum" :error="$errors->first('form.stok_minimum')" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="form.lacak_stok" class="rounded border-slate-300 text-emerald-600">
                        Lacak stok (matikan untuk jasa seperti fotocopy/cetak)
                    </label>
                    @error('form.lacak_stok') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
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
