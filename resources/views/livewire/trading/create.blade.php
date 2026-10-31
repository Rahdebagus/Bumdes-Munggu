@php
    $isJual = $jenis === 'penjualan';
    $pihakLabel = $isJual ? 'Pelanggan' : 'Pemasok';
@endphp
<div class="space-y-6">
    <x-ui.breadcrumb :items="[
        ['label' => 'Transaksi'],
        ['label' => $config['draft'] ? 'Edit draft '.$config['draft']['nomor'] : ucfirst($jenis).' baru'],
    ]" />

    {{-- Seluruh form dikelola Alpine (tanpa reload); wire:ignore agar Livewire tidak menyentuh DOM-nya. --}}
    <div wire:ignore>
        <form x-data="tradingForm(@js($config))" x-effect="persist()" @submit.prevent="save(true)" @keydown="onKeydown($event)" class="space-y-6" novalidate>

            <div x-show="message" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="message"></div>

            <x-ui.card :title="'Informasi '.ucfirst($jenis)" :subtitle="$isJual ? 'Menjual barang/jasa: stok berkurang, pendapatan & kas/piutang naik.' : 'Membeli barang dagangan dari pemasok: persediaan naik, kas turun atau hutang usaha naik.'">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700" for="f-tanggal">Tanggal</label>
                        <input id="f-tanggal" type="date" x-model="header.tanggal" :aria-invalid="!!err('tanggal')"
                               class="block w-full rounded-lg border px-3 py-2 text-sm" :class="err('tanggal') ? 'border-red-400' : 'border-slate-300'">
                        <p class="text-xs text-red-600" x-text="err('tanggal')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700" for="f-unit">Unit Usaha</label>
                        <select id="f-unit" x-model.number="header.unit_usaha_id" :aria-invalid="!!err('unit_usaha_id')"
                                class="block w-full rounded-lg border px-3 py-2 text-sm" :class="err('unit_usaha_id') ? 'border-red-400' : 'border-slate-300'">
                            <option value="">— pilih (otomatis dari produk) —</option>
                            <template x-for="u in config.units" :key="u.id">
                                <option :value="u.id" x-text="u.nama" :selected="u.id == header.unit_usaha_id"></option>
                            </template>
                        </select>
                        <p class="text-xs text-red-600" x-text="err('unit_usaha_id')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700" for="f-metode">Metode</label>
                        <select id="f-metode" x-model="header.metode_pembayaran" @change="onMetodeChange()"
                                class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="tunai">Tunai</option>
                            <option value="kredit">{{ $isJual ? 'Kredit / Piutang' : 'Kredit / Hutang' }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700" for="f-pihak">
                            {{ $pihakLabel }} <span class="text-xs font-normal text-slate-400" x-text="header.metode_pembayaran === 'kredit' ? '(wajib)' : '(opsional)'"></span>
                        </label>
                        <select id="f-pihak" x-model.number="header.pihak_id" :aria-invalid="!!err('pihak_id')" @change="delete errors.pihak_id"
                                class="block w-full rounded-lg border px-3 py-2 text-sm" :class="err('pihak_id') ? 'border-red-400' : 'border-slate-300'">
                            <option value="">{{ $isJual ? '— umum / tanpa nama —' : '— pilih pemasok —' }}</option>
                            <template x-for="p in config.pihak" :key="p.id">
                                <option :value="p.id" x-text="p.nama" :selected="p.id == header.pihak_id"></option>
                            </template>
                        </select>
                        <p class="text-xs text-red-600" x-text="err('pihak_id')"></p>
                        @if (empty($config['pihak']))
                            <p class="text-xs text-slate-500">Belum ada {{ strtolower($pihakLabel) }}. Tambah di <a href="{{ route('master.parties') }}" wire:navigate class="text-emerald-700 underline">Data Pihak</a>.</p>
                        @endif
                    </div>
                    <div class="space-y-1" x-show="header.metode_pembayaran === 'tunai'">
                        <label class="block text-sm font-medium text-slate-700" for="f-kas">Akun Kas/Bank</label>
                        <select id="f-kas" x-model.number="header.akun_kas_id" :aria-invalid="!!err('akun_kas_id')"
                                class="block w-full rounded-lg border px-3 py-2 text-sm" :class="err('akun_kas_id') ? 'border-red-400' : 'border-slate-300'">
                            <option value="">— pilih —</option>
                            <template x-for="a in config.akunKas" :key="a.id">
                                <option :value="a.id" x-text="a.label" :selected="a.id == header.akun_kas_id"></option>
                            </template>
                        </select>
                        <p class="text-xs text-red-600" x-text="err('akun_kas_id')"></p>
                    </div>
                    <div class="space-y-1" x-show="header.metode_pembayaran === 'kredit'" x-cloak>
                        <label class="block text-sm font-medium text-slate-700" for="f-jt">Jatuh Tempo</label>
                        <input id="f-jt" type="date" x-model="header.jatuh_tempo" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <p class="text-xs text-slate-500">{{ $isJual ? 'Masuk Piutang Usaha.' : 'Masuk Hutang Usaha — dilunasi di menu Pelunasan Hutang.' }}</p>
                    </div>
                    <div class="space-y-1 sm:col-span-2 lg:col-span-1">
                        <label class="block text-sm font-medium text-slate-700" for="f-ket">Keterangan</label>
                        <input id="f-ket" type="text" x-model="header.keterangan" maxlength="255" placeholder="mis. Pesanan rapat desa"
                               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Rincian Barang" subtitle="Ketik nama produk → pilih dengan ↑ ↓ lalu Enter. Alt+N tambah baris, Ctrl+Enter simpan.">
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-xs font-semibold text-slate-500">
                                <th class="w-8 px-2 py-2 text-center">#</th>
                                <th class="min-w-64 px-3 py-2 text-left">Produk</th>
                                <th class="w-24 px-3 py-2 text-right">Qty</th>
                                <th class="w-32 px-3 py-2 text-right">{{ $isJual ? 'Harga Jual' : 'Harga Beli' }}</th>
                                <th class="w-28 px-3 py-2 text-right">Diskon</th>
                                <th class="w-28 px-3 py-2 text-right">Pajak</th>
                                <th class="w-36 px-3 py-2 text-right">Subtotal</th>
                                <th class="w-10 px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(row, i) in rows" :key="row.key">
                                <tr class="align-top" :class="stokKurang(row) ? 'bg-amber-50/60' : ''">
                                    <td class="px-2 py-2 text-center text-xs text-slate-400" x-text="i + 1"></td>
                                    <td class="px-3 py-1.5">
                                        {{-- Combobox produk --}}
                                        <div class="relative">
                                            <input type="text" :id="`search-${row.key}`" x-model="row.search" autocomplete="off" data-combobox
                                                   placeholder="Ketik nama / kode produk…"
                                                   role="combobox" :aria-expanded="row.open" :aria-controls="`list-${row.key}`"
                                                   :aria-invalid="!!err(`items.${i}.produk_id`)"
                                                   @input="onSearchInput(row)" @focus="row.open = true; $event.target.select()"
                                                   @blur="closeLater(row)" @keydown="onSearchKey($event, row)"
                                                   class="w-full rounded-lg border px-2 py-1.5 text-sm"
                                                   :class="err(`items.${i}.produk_id`) ? 'border-red-400' : (row.produk_id ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-300')">
                                            <ul x-show="row.open && optionCount(row) > 0" x-cloak :id="`list-${row.key}`" role="listbox"
                                                @mousedown.prevent
                                                class="absolute left-0 z-30 mt-1 max-h-64 w-full min-w-80 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                                                <template x-for="(p, idx) in options(row)" :key="p.id">
                                                    <li :id="`opt-${row.key}-${idx}`" role="option" :aria-selected="row.active === idx"
                                                        @click="select(row, p)" @mouseenter="row.active = idx"
                                                        class="flex cursor-pointer items-baseline justify-between gap-3 px-3 py-1.5"
                                                        :class="row.active === idx ? 'bg-emerald-50 text-emerald-900' : 'text-slate-700'">
                                                        <span class="min-w-0">
                                                            <span class="font-medium" x-text="p.nama"></span>
                                                            <span class="text-xs text-slate-400" x-text="`(${p.satuan}) · ${p.kode}`"></span>
                                                            <span x-show="jenis === 'penjualan' && p.lacak_stok" class="block text-[11px]"
                                                                  :class="p.stok > 0 ? 'text-slate-400' : 'text-amber-600'" x-text="`stok ${qtyText(p.stok)}`"></span>
                                                        </span>
                                                        <span class="shrink-0 text-xs font-semibold tabular-nums" x-text="rupiah(hargaMaster(p))"></span>
                                                    </li>
                                                </template>
                                                <li x-show="canCreate(row)" :id="`opt-${row.key}-${options(row).length}`" role="option"
                                                    @click="openNewProduct(row)" @mouseenter="row.active = options(row).length"
                                                    class="cursor-pointer border-t border-slate-100 px-3 py-2 font-medium text-emerald-700"
                                                    :class="row.active === options(row).length ? 'bg-emerald-50' : ''">
                                                    + Tambah produk baru: <span x-text="`“${row.search.trim()}”`"></span>
                                                </li>
                                            </ul>
                                        </div>
                                        <p class="mt-0.5 text-[11px] text-slate-500" x-show="prod(row)">
                                            <span x-text="prod(row)?.satuan"></span>
                                            <template x-if="prod(row)?.lacak_stok">
                                                <span>· sisa stok <span class="font-medium" x-text="qtyText(prod(row)?.stok)"></span></span>
                                            </template>
                                            <template x-if="prod(row) && !prod(row).lacak_stok"><span>· jasa (tanpa stok)</span></template>
                                            <template x-if="jenis === 'penjualan' && prod(row)?.verifikasi"><span class="text-amber-600">· harga perlu verifikasi</span></template>
                                        </p>
                                        <p class="text-xs text-red-600" x-text="err(`items.${i}.produk_id`)"></p>
                                        <p x-show="stokKurang(row)" class="mt-0.5 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-800">
                                            Qty melebihi stok — akan dikonfirmasi sebagai pesanan (disimpan draft).
                                        </p>
                                        <p x-show="hargaBerbeda(row)" class="mt-0.5 text-[11px] text-amber-700">
                                            Harga beli master: <span x-text="rupiah(prod(row)?.harga_beli)"></span>
                                        </p>
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" :id="`qty-${row.key}`" step="any" min="0" inputmode="decimal" x-model="row.kuantitas"
                                               :aria-invalid="!!err(`items.${i}.kuantitas`)" @input="delete errors[`items.${i}.kuantitas`]"
                                               class="w-full rounded-lg border px-2 py-1.5 text-right text-sm tabular-nums"
                                               :class="err(`items.${i}.kuantitas`) ? 'border-red-400' : (stokKurang(row) ? 'border-amber-400' : 'border-slate-300')">
                                        <p class="text-xs text-red-600" x-text="err(`items.${i}.kuantitas`)"></p>
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" step="any" min="0" inputmode="decimal" x-model="row.harga"
                                               class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm tabular-nums">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" step="any" min="0" inputmode="decimal" x-model="row.diskon" :aria-invalid="!!err(`items.${i}.diskon`)"
                                               class="w-full rounded-lg border px-2 py-1.5 text-right text-sm tabular-nums"
                                               :class="err(`items.${i}.diskon`) ? 'border-red-400' : 'border-slate-300'">
                                        <p class="text-xs text-red-600" x-text="err(`items.${i}.diskon`)"></p>
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" step="any" min="0" inputmode="decimal" x-model="row.pajak" @keydown="lastFieldEnter($event, i)"
                                               class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm tabular-nums">
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium tabular-nums whitespace-nowrap"
                                        :class="subtotal(row) < 0 ? 'text-red-600' : 'text-slate-900'" x-text="rupiah(subtotal(row))"></td>
                                    <td class="px-2 py-1.5 text-center">
                                        <button type="button" @click="removeRow(i)" tabindex="-1" title="Hapus baris"
                                                class="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Hapus baris">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                    <x-ui.button variant="secondary" type="button" @click="addRow()" title="Alt+N">+ Baris</x-ui.button>
                    <dl class="min-w-64 space-y-1 text-sm">
                        <div class="flex justify-between gap-6 text-slate-500"><dt>Qty × Harga</dt><dd class="tabular-nums" x-text="rupiah(totalQtyHarga)"></dd></div>
                        <div class="flex justify-between gap-6 text-slate-500" x-show="totalDiskon > 0"><dt>Diskon</dt><dd class="tabular-nums" x-text="'− ' + rupiah(totalDiskon)"></dd></div>
                        <div class="flex justify-between gap-6 text-slate-500" x-show="totalPajak > 0"><dt>Pajak</dt><dd class="tabular-nums" x-text="'+ ' + rupiah(totalPajak)"></dd></div>
                        <div class="flex justify-between gap-6 border-t border-slate-200 pt-1 text-base font-semibold"
                             :class="total < 0 ? 'text-red-600' : 'text-slate-900'"><dt>Total</dt><dd class="tabular-nums" x-text="rupiah(total)"></dd></div>
                    </dl>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    @if ($config['canMutate'])
                        <span class="mr-auto text-xs text-slate-400">Isian tersimpan otomatis di browser ini sampai dokumen disimpan.</span>
                        <x-ui.button variant="secondary" type="button" @click="save(false)" ::disabled="saving || total < 0">Simpan Draft</x-ui.button>
                        <x-ui.button type="submit" ::disabled="saving || total < 0" title="Ctrl+Enter">
                            <span x-show="!saving">Simpan &amp; Posting</span>
                            <span x-show="saving" x-cloak>Menyimpan…</span>
                        </x-ui.button>
                    @else
                        <p class="text-sm text-slate-500">Hanya Admin yang dapat menyimpan transaksi.</p>
                    @endif
                </div>
            </x-ui.card>

            {{-- Modal tambah produk baru --}}
            <div x-show="modal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true"
                 @keydown.escape.stop="modal.open = false" @keydown.enter.stop.prevent="saveNewProduct()">
                <div class="absolute inset-0 bg-slate-900/50" @click="modal.open = false"></div>
                <div class="relative w-full max-w-md rounded-xl bg-white shadow-xl">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">Tambah produk baru</h2>
                    </div>
                    <div class="space-y-3 px-5 py-4">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-slate-700">Nama</label>
                            <input type="text" x-model="modal.nama_produk" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <p class="text-xs text-red-600" x-text="modal.errors.nama_produk?.[0]"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-slate-700">Satuan</label>
                                <input id="np-satuan" type="text" x-model="modal.satuan" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <p class="text-xs text-red-600" x-text="modal.errors.satuan?.[0]"></p>
                            </div>
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-slate-700">Kategori</label>
                                <select x-model="modal.kategori" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <template x-for="k in config.kategori" :key="k.value">
                                        <option :value="k.value" x-text="k.label" :selected="k.value === modal.kategori"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-slate-700">Harga Beli</label>
                                <input type="number" step="any" min="0" x-model="modal.harga_beli" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-right text-sm">
                                <p class="text-xs text-red-600" x-text="modal.errors.harga_beli?.[0]"></p>
                            </div>
                            <div class="space-y-1">
                                <label class="block text-sm font-medium text-slate-700">Harga Jual</label>
                                <input type="number" step="any" min="0" x-model="modal.harga_jual" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-right text-sm">
                                <p class="text-xs text-red-600" x-text="modal.errors.harga_jual?.[0]"></p>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">Kode produk dibuat otomatis sesuai kategori. Stok awal 0 — tambah lewat Pembelian.</p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">
                        <x-ui.button variant="secondary" type="button" @click="modal.open = false">Batal</x-ui.button>
                        <x-ui.button type="button" @click="saveNewProduct()" ::disabled="modal.saving">Simpan &amp; Pilih</x-ui.button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
