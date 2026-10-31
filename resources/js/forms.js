/*
 * Komponen Alpine untuk form transaksi SIA BUMDes.
 *
 * Prinsip (keluhan "halaman reload & lompat ke atas"):
 * - State form (baris barang, total, autocomplete) hidup di browser; tambah/
 *   hapus baris, pilih produk, dan hitung total TANPA request server.
 * - Server hanya dipanggil saat simpan ($wire.save) dan tambah produk baru;
 *   method Livewire memakai skipRender sehingga DOM & posisi scroll tidak
 *   disentuh.
 * - Autosave isian ke localStorage; pulih otomatis bila browser tertutup.
 * - Pintasan: Enter = field berikutnya, Ctrl+Enter = Simpan & Posting,
 *   Alt+N = tambah baris.
 *
 * Komponen didaftarkan pada event alpine:init (Livewire memulai Alpine saat
 * DOMContentLoaded, setelah modul Vite ini dieksekusi).
 */

const uid = () => Math.random().toString(36).slice(2, 10);

/** Angka dari input ("17500", "17.500", "17,5") → Number. */
export const num = (v) => {
    if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
    const s = String(v ?? '').trim();
    if (s === '') return 0;
    const n = parseFloat(/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(s) ? s.replace(/\./g, '').replace(',', '.') : s.replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
};

/** Format tampilan Rupiah: Rp 1.750.000 (desimal hanya bila ada). */
export const rupiah = (v) => 'Rp ' + num(v).toLocaleString('id-ID', { maximumFractionDigits: 2 });

const qtyText = (v) => num(v).toLocaleString('id-ID', { maximumFractionDigits: 4 });

window.rupiah = rupiah;
window.num = num;

/** Tampilkan toast: toast('Tersimpan', {type: 'success'|'error'|'warning'|'info', actions: [{label, href, target, navigate}]}) */
window.toast = (message, opts = {}) => window.Alpine?.store('toasts')?.push({ message, ...opts });

/** Fokus ke field berikutnya yang terlihat di dalam root (Enter = pindah field). */
function focusNextField(root, el) {
    const fields = [...root.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=file]):not([disabled]), select:not([disabled]), textarea:not([disabled])')]
        .filter((x) => x.offsetParent !== null && !x.readOnly);
    const i = fields.indexOf(el);
    if (i >= 0 && i < fields.length - 1) {
        fields[i + 1].focus();
        fields[i + 1].select?.();
        return true;
    }
    return false;
}

function storageGet(key) {
    try {
        return JSON.parse(window.localStorage.getItem(key) ?? 'null');
    } catch {
        return null;
    }
}

function storageSet(key, value) {
    try {
        value === null ? window.localStorage.removeItem(key) : window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        /* storage penuh/diblokir: autosave dilewati */
    }
}

/** Jatuh tempo default: penjualan +30 hari; pembelian = bayar borongan berikutnya (10/20/akhir bulan). */
function defaultJatuhTempo(jenis, tanggal) {
    const t = new Date((tanggal || new Date().toISOString().slice(0, 10)) + 'T00:00:00');
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    if (jenis === 'penjualan') {
        t.setDate(t.getDate() + 30);
        return iso(t);
    }
    const last = new Date(t.getFullYear(), t.getMonth() + 1, 0).getDate();
    for (const day of [10, 20, last]) {
        if (t.getDate() < day) return iso(new Date(t.getFullYear(), t.getMonth(), day));
    }
    return iso(new Date(t.getFullYear(), t.getMonth() + 1, 10));
}

// Sidebar: pertahankan posisi scroll menu saat pindah halaman (wire:navigate)
// dan pastikan menu aktif terlihat.
document.addEventListener('livewire:navigating', () => {
    const nav = document.querySelector('[data-sidebar-nav]');
    if (nav) window.sessionStorage.setItem('sidebar-scroll', String(nav.scrollTop));
});
document.addEventListener('livewire:navigated', () => {
    const nav = document.querySelector('[data-sidebar-nav]');
    if (!nav) return;
    const saved = window.sessionStorage.getItem('sidebar-scroll');
    if (saved !== null) nav.scrollTop = Number(saved);
    nav.querySelector('[aria-current="page"]')?.scrollIntoView({ block: 'nearest' });
});

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    // ---------------------------------------------------------------- toast
    Alpine.store('toasts', {
        items: [],
        push(t) {
            const id = uid();
            this.items.push({ id, type: 'success', actions: [], ...t });
            setTimeout(() => this.remove(id), t.timeout ?? (t.actions?.length ? 10000 : 5000));
        },
        remove(id) {
            this.items = this.items.filter((x) => x.id !== id);
        },
    });

    // Livewire: $this->dispatch('toast', message: '…', type: 'error')
    window.addEventListener('toast', (e) => {
        const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
        if (d?.message) window.toast(d.message, d);
    });

    // ------------------------------------------------------- trading form
    const emptyRow = () => ({ key: uid(), produk_id: null, search: '', kuantitas: '1', harga: '0', diskon: '0', pajak: '0', open: false, active: 0 });

    Alpine.data('tradingForm', (config) => ({
        config,
        jenis: config.jenis,
        produk: config.produk,
        header: {},
        rows: [],
        errors: {},
        message: '',
        saving: false,
        modal: { open: false, rowKey: null, nama_produk: '', satuan: 'Pcs', kategori: '', harga_beli: '0', harga_jual: '0', errors: {}, saving: false },
        rupiah,
        qtyText,

        init() {
            this.resetForm(false);

            if (config.draft) {
                this.header = { ...this.header, ...config.draft.header };
                this.rows = config.draft.items.map((it) => {
                    const p = this.produkById(it.produk_id);
                    return { ...emptyRow(), ...it, search: p?.nama ?? '' };
                });
                if (this.rows.length === 0) this.rows = [emptyRow()];
            }

            const saved = storageGet(config.storageKey);
            if (saved && (!config.draft || saved.draftId === config.draft.id) && this.isDirtyState(saved)) {
                this.header = { ...this.header, ...saved.header };
                this.rows = saved.rows.map((r) => ({ ...emptyRow(), ...r, key: uid(), open: false }));
                window.toast('Isian yang belum disimpan dipulihkan.', {
                    type: 'info',
                    actions: [{ label: 'Buang', onClick: () => { this.resetForm(false); storageSet(config.storageKey, null); } }],
                });
            }
        },

        // ---- data -------------------------------------------------------
        produkById(id) {
            return this.produk.find((p) => p.id === Number(id)) ?? null;
        },
        prod(row) {
            return row.produk_id ? this.produkById(row.produk_id) : null;
        },
        options(row) {
            const q = (row.search || '').trim().toLowerCase();
            const list = q ? this.produk.filter((p) => `${p.nama} ${p.kode}`.toLowerCase().includes(q)) : this.produk;
            return list.slice(0, 60);
        },
        canCreate(row) {
            const q = (row.search || '').trim().toLowerCase();
            return config.canMutate && q !== '' && !this.produk.some((p) => p.nama.toLowerCase() === q);
        },
        optionCount(row) {
            return this.options(row).length + (this.canCreate(row) ? 1 : 0);
        },
        hargaMaster(p) {
            return this.jenis === 'penjualan' ? p.harga_jual : p.harga_beli;
        },

        // ---- autocomplete -----------------------------------------------
        onSearchInput(row) {
            row.open = true;
            row.active = 0;
            const p = this.prod(row);
            if (p && p.nama !== row.search) row.produk_id = null;
        },
        onSearchKey(e, row) {
            const n = this.optionCount(row);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!row.open) { row.open = true; return; }
                row.active = Math.min(row.active + 1, n - 1);
                this.scrollActive(row);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                row.active = Math.max(row.active - 1, 0);
                this.scrollActive(row);
            } else if (e.key === 'Enter' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                if (row.open && n > 0) this.choose(row, row.active);
                else focusNextField(this.$root, e.target);
            } else if (e.key === 'Escape') {
                row.open = false;
            } else if (e.key === 'Tab') {
                if (row.open && !row.produk_id && this.options(row).length === 1) this.choose(row, 0);
                row.open = false;
            }
        },
        scrollActive(row) {
            this.$nextTick(() => document.getElementById(`opt-${row.key}-${row.active}`)?.scrollIntoView({ block: 'nearest' }));
        },
        choose(row, idx) {
            const opts = this.options(row);
            if (idx < opts.length) this.select(row, opts[idx]);
            else if (this.canCreate(row)) this.openNewProduct(row);
        },
        select(row, p) {
            row.produk_id = p.id;
            row.search = p.nama;
            row.harga = String(this.hargaMaster(p));
            if (!(num(row.kuantitas) > 0)) row.kuantitas = '1';
            row.open = false;
            if (!this.header.unit_usaha_id) this.header.unit_usaha_id = p.unit_id;
            this.clearRowErrors(row);
            this.$nextTick(() => {
                const el = document.getElementById(`qty-${row.key}`);
                el?.focus();
                el?.select();
            });
        },
        closeLater(row) {
            setTimeout(() => { row.open = false; }, 120);
        },

        // ---- tambah produk baru (modal) ---------------------------------
        openNewProduct(row) {
            row.open = false;
            const unitKategori = { 'Konsumsi / Jasa Boga': 'konsumsi', 'ATK & Fotocopy': 'atk', 'Upakara / Banten': 'upakara', Pupuk: 'pupuk' };
            const unit = config.units.find((u) => u.id === Number(this.header.unit_usaha_id));
            this.modal = {
                open: true, rowKey: row.key, nama_produk: row.search.trim(), satuan: 'Pcs',
                kategori: unitKategori[unit?.nama] ?? 'perdagangan', harga_beli: '0', harga_jual: '0', errors: {}, saving: false,
            };
            this.$nextTick(() => document.getElementById('np-satuan')?.focus());
        },
        async saveNewProduct() {
            if (this.modal.saving) return;
            this.modal.saving = true;
            const { open, rowKey, errors, saving, ...data } = this.modal;
            try {
                const res = await this.$wire.createProduct(data);
                if (!res.ok) {
                    this.modal.errors = res.errors ?? {};
                    return;
                }
                this.produk.push(res.produk);
                this.produk.sort((a, b) => a.nama.localeCompare(b.nama));
                const row = this.rows.find((r) => r.key === rowKey);
                this.modal.open = false;
                window.toast(`Produk ${res.produk.kode} — ${res.produk.nama} ditambahkan.`);
                if (row) this.select(row, res.produk);
            } finally {
                this.modal.saving = false;
            }
        },

        // ---- baris ------------------------------------------------------
        addRow() {
            const row = emptyRow();
            this.rows.push(row);
            this.$nextTick(() => document.getElementById(`search-${row.key}`)?.focus({ preventScroll: false }));
        },
        removeRow(i) {
            if (this.rows.length > 1) this.rows.splice(i, 1);
            else this.rows = [emptyRow()];
            this.errors = {};
        },
        lastFieldEnter(e, i) {
            // Enter di kolom terakhir baris terakhir → baris baru.
            if (e.key !== 'Enter' || e.ctrlKey || e.metaKey) return;
            if (i === this.rows.length - 1) {
                e.preventDefault();
                this.addRow();
            }
        },

        // ---- hitung -----------------------------------------------------
        subtotal(row) {
            return num(row.kuantitas) * num(row.harga) - num(row.diskon) + num(row.pajak);
        },
        get total() {
            return this.rows.reduce((s, r) => s + this.subtotal(r), 0);
        },
        get totalQtyHarga() {
            return this.rows.reduce((s, r) => s + num(r.kuantitas) * num(r.harga), 0);
        },
        get totalDiskon() {
            return this.rows.reduce((s, r) => s + num(r.diskon), 0);
        },
        get totalPajak() {
            return this.rows.reduce((s, r) => s + num(r.pajak), 0);
        },
        stokKurang(row) {
            const p = this.prod(row);
            return this.jenis === 'penjualan' && p && p.lacak_stok && num(row.kuantitas) > p.stok;
        },
        hargaBerbeda(row) {
            const p = this.prod(row);
            return this.jenis === 'pembelian' && p && num(row.harga) !== num(p.harga_beli);
        },

        // ---- header -----------------------------------------------------
        onMetodeChange() {
            if (this.header.metode_pembayaran === 'kredit') {
                if (!this.header.jatuh_tempo) this.header.jatuh_tempo = defaultJatuhTempo(this.jenis, this.header.tanggal);
            } else if (!this.header.akun_kas_id) {
                this.header.akun_kas_id = config.defaultKasId;
            }
            delete this.errors.pihak_id;
            delete this.errors.akun_kas_id;
        },

        // ---- validasi & simpan ------------------------------------------
        err(key) {
            return this.errors[key]?.[0] ?? '';
        },
        clearRowErrors(row) {
            const i = this.rows.indexOf(row);
            for (const k of Object.keys(this.errors)) if (k.startsWith(`items.${i}.`)) delete this.errors[k];
        },
        isBlank(row) {
            return !row.produk_id && (row.search || '').trim() === '';
        },
        filledRows() {
            const rows = this.rows.filter((r) => !this.isBlank(r));
            return rows.length ? rows : this.rows;
        },
        validateClient() {
            const e = {};
            this.rows.forEach((r, i) => {
                if (this.isBlank(r) && this.rows.some((x) => !this.isBlank(x))) return; // baris kosong diabaikan
                if (!r.produk_id) e[`items.${i}.produk_id`] = [r.search ? 'Produk tidak ditemukan — pilih dari daftar atau tambah produk baru.' : 'Pilih produk.'];
                if (!(num(r.kuantitas) > 0)) e[`items.${i}.kuantitas`] = ['Qty harus > 0.'];
                if (num(r.kuantitas) * num(r.harga) - num(r.diskon) < 0) e[`items.${i}.diskon`] = ['Diskon melebihi qty × harga.'];
            });
            if (!this.header.unit_usaha_id) e.unit_usaha_id = ['Pilih unit usaha.'];
            if (!this.header.tanggal) e.tanggal = ['Tanggal wajib.'];
            if (this.header.metode_pembayaran === 'tunai' && !this.header.akun_kas_id) e.akun_kas_id = ['Pilih akun kas/bank.'];
            if (this.header.metode_pembayaran === 'kredit' && !this.header.pihak_id) {
                e.pihak_id = [this.jenis === 'penjualan' ? 'Penjualan kredit wajib memilih pelanggan.' : 'Pembelian kredit wajib memilih pemasok.'];
            }
            this.errors = e;
            return Object.keys(e).length === 0;
        },
        payload() {
            // Indeks baris server = indeks di this.rows agar pesan galat jatuh di baris yang benar.
            const rows = this.filledRows();
            return {
                ...this.header,
                pihak_id: this.header.pihak_id || null,
                akun_kas_id: this.header.akun_kas_id || null,
                jatuh_tempo: this.header.jatuh_tempo || null,
                items: rows.map((r) => ({ produk_id: r.produk_id, kuantitas: String(num(r.kuantitas)), harga: String(num(r.harga)), diskon: String(num(r.diskon)), pajak: String(num(r.pajak)) })),
            };
        },
        focusFirstError() {
            this.$nextTick(() => {
                const el = this.$root.querySelector('[aria-invalid="true"]');
                el?.focus();
                el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            });
        },
        async save(post = true) {
            if (this.saving || !config.canMutate) return;
            this.message = '';
            this.rows = this.filledRows();
            if (!this.validateClient()) {
                this.message = 'Periksa kembali isian yang ditandai merah.';
                this.focusFirstError();
                return;
            }

            if (post && this.jenis === 'penjualan') {
                const kurang = this.rows.filter((r) => this.stokKurang(r));
                if (kurang.length) {
                    const daftar = kurang.map((r) => `• ${this.prod(r).nama}: diminta ${qtyText(r.kuantitas)}, stok ${qtyText(this.prod(r).stok)}`).join('\n');
                    if (!window.confirm(`Stok tidak cukup:\n${daftar}\n\nLanjutkan sebagai barang PESANAN? Dokumen disimpan sebagai DRAFT dan bisa diposting setelah pembelian/stok dicatat.`)) return;
                    post = false;
                }
            }

            let updateHarga = false;
            if (this.jenis === 'pembelian') {
                const beda = this.rows.filter((r) => this.hargaBerbeda(r));
                if (beda.length) {
                    const daftar = beda.map((r) => `• ${this.prod(r).nama}: ${rupiah(this.prod(r).harga_beli)} → ${rupiah(r.harga)}`).join('\n');
                    updateHarga = window.confirm(`Harga beli berbeda dari master:\n${daftar}\n\nPerbarui harga beli master?`);
                }
            }

            this.saving = true;
            let res;
            try {
                res = await this.$wire.save(this.payload(), post, updateHarga);
            } catch (err) {
                window.toast('Gagal menyimpan — periksa koneksi lalu coba lagi.', { type: 'error' });
                return;
            } finally {
                this.saving = false;
            }

            if (!res?.ok) {
                this.errors = res?.errors ?? {};
                this.message = res?.message ?? 'Gagal menyimpan.';
                window.toast(this.message, { type: 'error' });
                this.focusFirstError();
                return;
            }

            for (const [id, stok] of Object.entries(res.stok ?? {})) {
                const p = this.produkById(id);
                if (p) p.stok = stok;
            }
            for (const [id, harga] of Object.entries(res.hargaBeli ?? {})) {
                const p = this.produkById(id);
                if (p) p.harga_beli = num(harga);
            }

            const actions = [
                { label: 'Lihat Nota', href: res.showUrl, navigate: true },
                { label: 'Cetak', href: res.notaUrl, target: '_blank' },
            ];
            window.toast(res.warning ?? `Transaksi tersimpan${res.posted ? ' & diposting' : ' (draft)'} — No. Nota ${res.nomor}`, {
                type: res.warning ? 'warning' : 'success',
                actions,
            });

            storageSet(config.storageKey, null);
            if (config.draft) {
                window.Livewire.navigate(res.showUrl);
                return;
            }
            this.resetForm(true);
            this.$nextTick(() => document.getElementById(`search-${this.rows[0].key}`)?.focus());
        },

        // ---- reset & autosave -------------------------------------------
        resetForm(keepHeader) {
            const keep = keepHeader ? this.header : {};
            this.header = {
                tanggal: keep.tanggal ?? config.today,
                unit_usaha_id: keep.unit_usaha_id ?? '',
                pihak_id: '',
                metode_pembayaran: keep.metode_pembayaran ?? 'tunai',
                akun_kas_id: keep.akun_kas_id ?? config.defaultKasId ?? '',
                jatuh_tempo: keep.metode_pembayaran === 'kredit' ? defaultJatuhTempo(this.jenis, keep.tanggal ?? config.today) : '',
                keterangan: '',
            };
            this.rows = [emptyRow()];
            this.errors = {};
            this.message = '';
        },
        isDirtyState(state) {
            return state?.rows?.some((r) => r.produk_id || (r.search || '').trim() !== '') || (state?.header?.keterangan ?? '') !== '';
        },
        persist() {
            const state = {
                draftId: config.draft?.id ?? null,
                header: { ...this.header },
                rows: this.rows.map(({ produk_id, search, kuantitas, harga, diskon, pajak }) => ({ produk_id, search, kuantitas, harga, diskon, pajak })),
            };
            storageSet(config.storageKey, this.isDirtyState(state) ? state : null);
        },

        // ---- pintasan keyboard ------------------------------------------
        onKeydown(e) {
            if (e.defaultPrevented) return;
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                this.save(true);
            } else if (e.altKey && (e.key === 'n' || e.key === 'N')) {
                e.preventDefault();
                this.addRow();
            } else if (e.key === 'Enter' && !e.shiftKey && !['TEXTAREA', 'BUTTON', 'A'].includes(e.target.tagName)) {
                e.preventDefault();
                focusNextField(this.$root, e.target);
            }
        },
    }));

    // ----------------------------------------------------------- cash form
    Alpine.data('cashForm', (config) => ({
        config,
        header: {},
        allocations: [],
        errors: {},
        message: '',
        saving: false,
        rupiah,
        num,

        init() {
            this.resetForm(false);
            if (config.draft) this.applyState(config.draft);

            const saved = storageGet(config.storageKey);
            if (saved && (!config.draft || saved.draftId === config.draft.id) && this.isDirtyState(saved)) {
                this.applyState(saved);
                window.toast('Isian yang belum disimpan dipulihkan.', {
                    type: 'info',
                    actions: [{ label: 'Buang', onClick: () => { this.resetForm(false); storageSet(config.storageKey, null); } }],
                });
            }
        },
        applyState(state) {
            this.header = { ...this.header, ...state.header };
            this.allocations = (state.allocations ?? []).map((a) => ({ key: uid(), ...a }));
            if (this.allocations.length === 0) this.allocations = [{ key: uid(), invoice_transaksi_id: '', nominal: '0' }];
        },

        get kategori() {
            return config.categories[this.header.kategori_kode] ?? null;
        },
        get arah() {
            return this.kategori?.arah ?? 'masuk';
        },
        get isPelunasan() {
            return ['pelunasan_piutang', 'pelunasan_utang'].includes(this.header.kategori_kode);
        },
        get lawanOptions() {
            return this.kategori?.lawanOptions ?? [];
        },
        get outstanding() {
            return config.outstanding[this.header.kategori_kode] ?? [];
        },
        get allocTotal() {
            return this.allocations.reduce((s, a) => s + num(a.nominal), 0);
        },
        get jualBeliWarning() {
            const text = `${this.kategori?.label ?? ''} ${this.header.keterangan ?? ''}`.toLowerCase();
            if (/penjualan|jual barang|menjual/.test(text)) return { label: 'Penjualan', url: config.urls.sales };
            if (/pembelian barang|beli barang|membeli barang|belanja barang/.test(text)) return { label: 'Pembelian', url: config.urls.purchases };
            return null;
        },

        onKategoriChange() {
            const k = this.kategori;
            this.errors = {};
            if (!k || k.redirect) return;
            // Akun lawan & kas default otomatis (tetap bisa diubah).
            this.header.akun_lawan_id = k.defaultLawanId ?? (k.lawanOptions.length === 1 ? k.lawanOptions[0].id : '');
            if (k.defaultKasId) this.header.akun_kas_id = k.defaultKasId;
            if (this.isPelunasan && this.allocations.length === 0) this.allocations = [{ key: uid(), invoice_transaksi_id: '', nominal: '0' }];
        },
        onInvoiceChange(a) {
            const inv = this.outstanding.find((o) => o.id === Number(a.invoice_transaksi_id));
            if (inv && !(num(a.nominal) > 0)) a.nominal = String(inv.sisa);
        },
        addAllocation() {
            this.allocations.push({ key: uid(), invoice_transaksi_id: '', nominal: '0' });
        },
        removeAllocation(i) {
            if (this.allocations.length > 1) this.allocations.splice(i, 1);
        },
        err(key) {
            return this.errors[key]?.[0] ?? '';
        },

        validateClient() {
            const e = {};
            const k = this.kategori;
            if (!k) e.kategori_kode = ['Pilih kategori.'];
            if (k?.redirect) e.kategori_kode = [k.redirect.pesan];
            if (!this.header.tanggal) e.tanggal = ['Tanggal wajib.'];
            if (!this.header.akun_kas_id) e.akun_kas_id = ['Pilih akun kas/bank.'];
            if (this.isPelunasan) {
                this.allocations.forEach((a, i) => {
                    if (!a.invoice_transaksi_id) e[`allocations.${i}.invoice_transaksi_id`] = ['Pilih nota.'];
                    if (!(num(a.nominal) > 0)) e[`allocations.${i}.nominal`] = ['Nominal > 0.'];
                });
            } else if (k && !k.redirect) {
                if (!this.header.akun_lawan_id) e.akun_lawan_id = ['Pilih akun lawan.'];
                if (!(num(this.header.nominal) > 0)) e.nominal = ['Nominal harus > 0.'];
                if (String(this.header.akun_lawan_id) === String(this.header.akun_kas_id)) e.akun_lawan_id = ['Akun lawan tidak boleh sama dengan akun kas.'];
            }
            this.errors = e;
            return Object.keys(e).length === 0;
        },
        payload() {
            return {
                ...this.header,
                unit_usaha_id: this.header.unit_usaha_id || null,
                akun_lawan_id: this.header.akun_lawan_id || null,
                nominal: String(num(this.header.nominal)),
                allocations: this.isPelunasan ? this.allocations.map((a) => ({ invoice_transaksi_id: a.invoice_transaksi_id, nominal: String(num(a.nominal)) })) : [],
            };
        },
        async save(post = true) {
            if (this.saving || !config.canMutate) return;
            this.message = '';
            if (!this.validateClient()) {
                this.message = 'Periksa kembali isian yang ditandai merah.';
                return;
            }
            this.saving = true;
            let res;
            try {
                res = await this.$wire.save(this.payload(), post);
            } catch {
                window.toast('Gagal menyimpan — periksa koneksi lalu coba lagi.', { type: 'error' });
                return;
            } finally {
                this.saving = false;
            }
            if (!res?.ok) {
                this.errors = res?.errors ?? {};
                this.message = res?.message ?? 'Gagal menyimpan.';
                window.toast(this.message, { type: 'error' });
                return;
            }
            if (res.outstanding) config.outstanding = res.outstanding;
            window.toast(res.warning ?? `Transaksi tersimpan${res.posted ? ' & diposting' : ' (draft)'} — No. ${res.nomor}`, {
                type: res.warning ? 'warning' : 'success',
                actions: [
                    { label: 'Lihat Bukti', href: res.showUrl, navigate: true },
                    { label: 'Cetak', href: res.notaUrl, target: '_blank' },
                ],
            });
            storageSet(config.storageKey, null);
            if (config.draft) {
                window.Livewire.navigate(res.showUrl);
                return;
            }
            this.resetForm(true);
        },
        resetForm(keepHeader) {
            const keep = keepHeader ? this.header : {};
            this.header = {
                tanggal: keep.tanggal ?? config.today,
                kategori_kode: keep.kategori_kode ?? config.defaultKategori,
                unit_usaha_id: keep.unit_usaha_id ?? '',
                akun_kas_id: keep.akun_kas_id ?? config.defaultKasId ?? '',
                akun_lawan_id: keep.akun_lawan_id ?? '',
                nominal: '',
                keterangan: '',
            };
            this.allocations = [{ key: uid(), invoice_transaksi_id: '', nominal: '0' }];
            this.errors = {};
            if (!keepHeader) this.onKategoriChange();
        },
        isDirtyState(state) {
            return num(state?.header?.nominal) > 0 || (state?.header?.keterangan ?? '') !== '' || state?.allocations?.some((a) => a.invoice_transaksi_id);
        },
        persist() {
            const state = {
                draftId: config.draft?.id ?? null,
                header: { ...this.header },
                allocations: this.allocations.map(({ invoice_transaksi_id, nominal }) => ({ invoice_transaksi_id, nominal })),
            };
            storageSet(config.storageKey, this.isDirtyState(state) ? state : null);
        },
        onKeydown(e) {
            if (e.defaultPrevented) return;
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                this.save(true);
            } else if (e.altKey && (e.key === 'n' || e.key === 'N') && this.isPelunasan) {
                e.preventDefault();
                this.addAllocation();
            } else if (e.key === 'Enter' && !e.shiftKey && !['TEXTAREA', 'BUTTON', 'A'].includes(e.target.tagName)) {
                e.preventDefault();
                focusNextField(this.$root, e.target);
            }
        },
    }));
});
