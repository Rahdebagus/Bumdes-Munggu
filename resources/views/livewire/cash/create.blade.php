@php
    $grupLabel = ['utama' => 'Buku kas BUMDes', 'lainnya' => 'Lainnya'];
    $byArah = ['masuk' => [], 'keluar' => []];
    foreach ($config['categories'] as $kode => $k) {
        $byArah[$k['arah']][$k['grup']][$kode] = $k;
    }
@endphp
<div class="space-y-6">
    <x-ui.breadcrumb :items="[['label' => 'Transaksi'], ['label' => $config['draft'] ? 'Edit draft kas' : 'Pemasukan & Pengeluaran']]" />

    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        <p class="font-medium">Form ini untuk kas masuk/keluar di luar jual-beli barang.</p>
        <p class="mt-0.5 text-blue-800">Untuk menjual barang gunakan menu
            <a href="{{ route('sales.create') }}" wire:navigate class="font-semibold underline">Penjualan</a>, untuk membeli barang gunakan menu
            <a href="{{ route('purchases.create') }}" wire:navigate class="font-semibold underline">Pembelian</a>.
            Contoh di sini: bunga &amp; admin bank, penambahan kas dari bank, gaji, transport, biaya kantor, dana sosial, pendapatan Desa Wisata, fee pupuk.</p>
    </div>

    <div wire:ignore>
        <form x-data="cashForm(@js($config))" x-effect="persist()" @submit.prevent="save(true)" @keydown="onKeydown($event)" class="space-y-6" novalidate>
            <div x-show="message" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="message"></div>

            <x-ui.card title="Transaksi Kas">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-1 sm:col-span-2 lg:col-span-1">
                        <label class="block text-sm font-medium text-slate-700" for="k-kategori">Kategori</label>
                        <select id="k-kategori" x-model="header.kategori_kode" @change="onKategoriChange()" :aria-invalid="!!err('kategori_kode')"
                                class="block w-full rounded-lg border px-3 py-2 text-sm" :class="err('kategori_kode') ? 'border-red-400' : 'border-slate-300'">
                            @foreach (['masuk' => 'KAS MASUK', 'keluar' => 'KAS KELUAR'] as $arah => $arahLabel)
                                @foreach ($byArah[$arah] as $grup => $items)
                                    <optgroup label="{{ $arahLabel }} — {{ $grupLabel[$grup] ?? $grup }}">
                                        @foreach ($items as $kode => $k)
                                            <option value="{{ $kode }}">{{ $k['label'] }}{{ $k['redirect'] ? ' → menu '.$k['redirect']['label'] : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            @endforeach
                        </select>
                        <p class="text-xs" :class="arah === 'masuk' ? 'text-emerald-700' : 'text-rose-700'"
                           x-text="arah === 'masuk' ? 'Kas masuk: D Kas/Bank — K akun lawan' : 'Kas keluar: D akun lawan — K Kas/Bank'"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700" for="k-tanggal">Tanggal</label>
                        <input id="k-tanggal" type="date" x-model="header.tanggal" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <p class="text-xs text-red-600" x-text="err('tanggal')"></p>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700" for="k-unit">Unit Usaha <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
                        <select id="k-unit" x-model.number="header.unit_usaha_id" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— umum / BUMDes —</option>
                            <template x-for="u in config.units" :key="u.id">
                                <option :value="u.id" x-text="u.nama" :selected="u.id == header.unit_usaha_id"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Kategori yang dicatat di menu lain --}}
                <template x-if="kategori?.redirect">
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p x-text="kategori.redirect.pesan"></p>
                        <a :href="kategori.redirect.url" wire:navigate class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 font-semibold text-white hover:bg-amber-700"
                           x-text="`Buka menu ${kategori.redirect.label} →`"></a>
                    </div>
                </template>

                {{-- Peringatan: kategori/keterangan terdengar seperti jual-beli barang --}}
                <template x-if="!kategori?.redirect && jualBeliWarning">
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p>Sepertinya ini transaksi jual-beli barang. Gunakan menu <strong x-text="jualBeliWarning.label"></strong> agar stok &amp; akun penjualan/persediaan ikut tercatat.</p>
                        <a :href="jualBeliWarning.url" wire:navigate class="shrink-0 rounded-lg bg-amber-600 px-3 py-1.5 font-semibold text-white hover:bg-amber-700"
                           x-text="`Buka ${jualBeliWarning.label} →`"></a>
                    </div>
                </template>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" x-show="!kategori?.redirect">
                    <div class="space-y-1">
                        <label class="block text-sm font-medium text-slate-700" for="k-kas" x-text="arah === 'masuk' ? 'Masuk ke Kas/Bank' : 'Dibayar dari Kas/Bank'"></label>
                        <select id="k-kas" x-model.number="header.akun_kas_id" :aria-invalid="!!err('akun_kas_id')"
                                class="block w-full rounded-lg border px-3 py-2 text-sm" :class="err('akun_kas_id') ? 'border-red-400' : 'border-slate-300'">
                            <option value="">— pilih —</option>
                            <template x-for="a in config.akunKas" :key="a.id">
                                <option :value="a.id" x-text="a.label" :selected="a.id == header.akun_kas_id"></option>
                            </template>
                        </select>
                        <p class="text-xs text-red-600" x-text="err('akun_kas_id')"></p>
                    </div>

                    <template x-if="!isPelunasan">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-slate-700" for="k-lawan">Akun Lawan <span class="text-xs font-normal text-slate-400">(otomatis, bisa diubah)</span></label>
                            <select id="k-lawan" x-model.number="header.akun_lawan_id" :aria-invalid="!!err('akun_lawan_id')"
                                    class="block w-full rounded-lg border px-3 py-2 text-sm" :class="err('akun_lawan_id') ? 'border-red-400' : 'border-slate-300'">
                                <option value="">— pilih —</option>
                                <template x-for="a in lawanOptions" :key="a.id">
                                    <option :value="a.id" x-text="a.label" :selected="a.id == header.akun_lawan_id"></option>
                                </template>
                            </select>
                            <p class="text-xs text-red-600" x-text="err('akun_lawan_id')"></p>
                        </div>
                    </template>

                    <template x-if="!isPelunasan">
                        <div class="space-y-1">
                            <label class="block text-sm font-medium text-slate-700" for="k-nominal">Nominal (Rp)</label>
                            <input id="k-nominal" type="number" step="any" min="0" inputmode="decimal" x-model="header.nominal" placeholder="0"
                                   :aria-invalid="!!err('nominal')"
                                   class="block w-full rounded-lg border px-3 py-2 text-right text-sm tabular-nums" :class="err('nominal') ? 'border-red-400' : 'border-slate-300'">
                            <p class="text-xs text-slate-500" x-show="num(header.nominal) > 0" x-text="rupiah(header.nominal)"></p>
                            <p class="text-xs text-red-600" x-text="err('nominal')"></p>
                        </div>
                    </template>

                    <div class="space-y-1 sm:col-span-2 lg:col-span-3">
                        <label class="block text-sm font-medium text-slate-700" for="k-ket">Keterangan</label>
                        <input id="k-ket" type="text" x-model="header.keterangan" maxlength="255" placeholder="mis. Gaji pegawai Oktober"
                               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>

                {{-- Pelunasan piutang / hutang: alokasi ke nota --}}
                <template x-if="isPelunasan">
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-700" x-text="header.kategori_kode === 'pelunasan_utang' ? 'Nota pembelian kredit yang dibayar' : 'Nota penjualan kredit yang dilunasi'"></p>
                            <a href="{{ route('settlements.payable') }}" wire:navigate x-show="header.kategori_kode === 'pelunasan_utang'" class="text-xs font-medium text-emerald-700 underline">Bayar banyak nota sekaligus →</a>
                        </div>
                        <p x-show="outstanding.length === 0" class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-500">Tidak ada nota kredit yang masih terbuka.</p>
                        <template x-for="(a, i) in allocations" :key="a.key">
                            <div class="flex flex-wrap items-start gap-2">
                                <div class="min-w-64 flex-1">
                                    <select x-model.number="a.invoice_transaksi_id" @change="onInvoiceChange(a)" :aria-invalid="!!err(`allocations.${i}.invoice_transaksi_id`)"
                                            class="block w-full rounded-lg border px-3 py-2 text-sm" :class="err(`allocations.${i}.invoice_transaksi_id`) ? 'border-red-400' : 'border-slate-300'">
                                        <option value="">— pilih nota —</option>
                                        <template x-for="o in outstanding" :key="o.id">
                                            <option :value="o.id" x-text="`${o.label} · sisa ${rupiah(o.sisa)}`" :selected="o.id == a.invoice_transaksi_id"></option>
                                        </template>
                                    </select>
                                    <p class="text-xs text-red-600" x-text="err(`allocations.${i}.invoice_transaksi_id`)"></p>
                                </div>
                                <div class="w-40">
                                    <input type="number" step="any" min="0" x-model="a.nominal" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-right text-sm tabular-nums">
                                    <p class="text-xs text-red-600" x-text="err(`allocations.${i}.nominal`)"></p>
                                </div>
                                <button type="button" @click="removeAllocation(i)" class="rounded p-2 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Hapus">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </template>
                        <div class="flex items-center justify-between">
                            <x-ui.button variant="secondary" type="button" @click="addAllocation()">+ Nota</x-ui.button>
                            <p class="text-sm font-semibold">Total: <span class="tabular-nums" x-text="rupiah(allocTotal)"></span></p>
                        </div>
                    </div>
                </template>

                <div class="mt-5 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4" x-show="!kategori?.redirect">
                    @if ($config['canMutate'])
                        <span class="mr-auto text-xs text-slate-400">Ctrl+Enter = Simpan &amp; Posting</span>
                        <x-ui.button variant="secondary" type="button" @click="save(false)" ::disabled="saving">Simpan Draft</x-ui.button>
                        <x-ui.button type="submit" ::disabled="saving">
                            <span x-show="!saving">Simpan &amp; Posting</span>
                            <span x-show="saving" x-cloak>Menyimpan…</span>
                        </x-ui.button>
                    @else
                        <p class="text-sm text-slate-500">Hanya Admin yang dapat menyimpan transaksi.</p>
                    @endif
                </div>
            </x-ui.card>
        </form>
    </div>
</div>
