# PROMPT UNTUK CLAUDE (SIA BUMDes Munggu)

Salin SELURUH isi file ini ke Claude Code / Copilot yang sedang memegang source code proyek.
Lampirkan juga file `produk_bumdes.csv` bila diminta.

---

## 0. KONTEKS PROYEK

Saya punya aplikasi web akuntansi **SIA BUMDes Munggu** (BUM Desa "Tri Guna Sejahtera", Desa Munggu, Kec. Mengwi, Kab. Badung) berbasis Laravel, jalan di `http://127.0.0.1:8001`. Menu yang sudah ada:

- **TRANSAKSI**: Penjualan, Pembelian, Pemasukan & Pengeluaran (Kas), Pembayaran Angsuran, Daftar Transaksi
- **USAHA KREDIT**: Data Peminjam, Data Pinjaman, Jadwal Angsuran
- **DATA MASTER**: Unit Usaha, Akun Akuntansi, Data Pihak, Data Produk

Unit usaha BUMDes: **Konsumsi/Jasa Boga, Perdagangan Umum, ATK & Fotocopy, Upakara/Banten, Pupuk, Desa Wisata, Kredit Pedagang Pantai**.

Kerjakan 6 tugas di bawah ini. Jangan ubah hal lain yang sudah jalan. Setelah selesai, tulis ringkasan file yang diubah.

---

## 1. TUGAS A — MASTER PRODUK HARUS TERISI SEMUA

**Masalah:** di form Penjualan dan Pembelian, produk harus dipilih satu-satu dari dropdown `— produk —` yang isinya masih kosong/sedikit. Harga juga masih diketik manual.

**Yang saya mau:**

1. Lengkapi tabel `products` (Data Produk) dengan kolom minimal:
   `id, kode, nama, satuan, kategori, harga_beli, harga_jual, stok, aktif`
   - `kategori` enum: `konsumsi`, `perdagangan`, `atk`, `upakara`, `jasa`, `pupuk`
   - `kode` dibuat otomatis (mis. `KSM-001`, `ATK-014`, `UPK-003`)
2. Buat **seeder** `ProdukBumdesSeeder` yang mengisi SEMUA produk di tabel Lampiran A (159 item) — nama, satuan, harga beli, harga jual, stok awal. Gunakan `updateOrCreate` berdasarkan `nama` supaya aman dijalankan berulang.
3. Buat juga **importer Excel/CSV** (`php artisan produk:import {file}`) supaya bulan depan tinggal upload file baru, tidak input manual.
4. Di halaman Data Produk: tabel bisa dicari, difilter per kategori, harga bisa diedit inline, dan ada tombol "Tambah Produk".

> Catatan harga jual: kolom `sumber_harga_jual` di lampiran menandai mana harga asli dari Excel dan mana hasil estimasi +10% yang masih harus dikoreksi admin. Tampilkan badge "perlu verifikasi" pada produk yang harga jualnya estimasi.

---

## 2. TUGAS B — FORM PENJUALAN: KETIK NAMA PRODUK, HARGA OTOMATIS

Ubah halaman `/penjualan/baru` (bagian **Rincian Barang**) jadi seperti ini:

1. Kolom **Produk** bukan `<select>` biasa, tapi **input teks dengan autocomplete** (combobox / searchable select):
   - Admin mengetik "nasi", muncul daftar: *Nasi Bungkus (Bks) – Rp 17.500*, *Nasi Kotak – Rp 25.000*, dst.
   - Pencarian tidak peduli huruf besar/kecil dan bisa cocok di tengah kata.
   - Bisa dipilih pakai panah atas/bawah + Enter, tanpa harus klik.
   - Kalau produk belum ada, tampilkan opsi **"+ Tambah produk baru: <teks yang diketik>"** yang membuka modal kecil (nama, satuan, kategori, harga beli, harga jual) dan langsung terpilih di baris itu.
2. Begitu produk dipilih:
   - **Harga** terisi otomatis dari `harga_jual` (boleh diubah manual kalau perlu, tapi default otomatis).
   - **Satuan** dan **sisa stok** ditampilkan sebagai teks kecil di bawah nama produk.
   - Qty default 1, fokus kursor langsung pindah ke kolom Qty.
3. Perhitungan **live tanpa reload**: `subtotal = (qty × harga) − diskon + pajak`, dan **Total** di kanan bawah langsung berubah setiap kali qty/harga/diskon diketik.
4. Tombol **+ Baris** menambah baris baru di bawah **tanpa me-reload halaman dan tanpa melompat ke atas**; tiap baris ada tombol hapus (ikon X).
5. Validasi: tidak boleh simpan kalau ada baris tanpa produk atau qty 0; kalau qty > stok, munculkan peringatan kuning (boleh lanjut untuk barang pesanan, tapi harus ada konfirmasi).
6. Metode = **Tunai** → wajib pilih Akun Kas/Bank. Metode = **Kredit/Piutang** → Akun Kas/Bank disembunyikan, wajib pilih Pelanggan (Data Pihak), dan transaksi masuk ke Piutang Usaha.
7. Saat disimpan: stok produk berkurang, dan jurnal otomatis terbentuk (lihat Tugas F).

---

## 3. TUGAS C — FORM PEMBELIAN: SAMA PERSIS TAPI PAKAI HARGA BELI

Terapkan semua poin Tugas B di `/pembelian/baru`, dengan perbedaan:

- Autocomplete mengisi **harga_beli** (bukan harga jual).
- Ada kolom **Pemasok** (dari Data Pihak).
- Metode **Tunai** → kas/bank berkurang; metode **Kredit** → masuk **Hutang Usaha** (sesuai praktik BUMDes sekarang: pembelian konsumsi dibayar borongan tiap tanggal 10/20/30, jadi harus bisa dicatat sebagai hutang dulu lalu dilunasi).
- Saat disimpan: stok bertambah, dan harga beli produk diperbarui kalau berbeda (dengan konfirmasi "Perbarui harga beli master?").
- Tambahkan halaman **Pelunasan Hutang Usaha** untuk membayar sekaligus beberapa nota pembelian kredit.

---

## 4. TUGAS D — PERJELAS BEDA "PEMASUKAN & PENGELUARAN" DENGAN "PENJUALAN/PEMBELIAN"

Supaya admin tidak bingung, tegaskan pembagian ini di aplikasi:

| Menu | Dipakai untuk | Pengaruh |
|---|---|---|
| **Penjualan** | Semua transaksi **menjual barang/jasa** (nasi, snack, ATK, upakara, pupuk, pakaian). Selalu ada produk & qty. | Pendapatan + Stok turun + Kas/Piutang naik |
| **Pembelian** | Semua transaksi **membeli barang dagangan** dari pemasok. Selalu ada produk & qty. | Persediaan naik + Kas turun/Hutang naik |
| **Pemasukan & Pengeluaran (Kas)** | Uang masuk/keluar yang **BUKAN jual beli barang**: bunga bank, admin & pajak bank, penambahan kas dari bank, biaya gaji pegawai, biaya transport, biaya kantor, dana sosial, penyusutan, setoran/penarikan antar kas–bank, pendapatan Desa Wisata, fee pupuk. Tidak ada produk & qty. | Kas/Bank + akun biaya/pendapatan lain, stok tidak berubah |

Yang harus dikerjakan:

1. Di halaman Pemasukan & Pengeluaran, tambahkan **keterangan singkat di atas form**: "Form ini untuk kas masuk/keluar di luar jual-beli barang. Untuk menjual barang gunakan menu Penjualan, untuk membeli barang gunakan menu Pembelian."
2. Kalau Kategori yang dipilih mengandung kata "penjualan"/"pembelian barang", tampilkan peringatan + tombol pintas ke menu yang benar.
3. Rapikan daftar **Kategori** pada form kas supaya sesuai buku kas BUMDes:
   - Masuk: `Penambahan Kas dari Bank`, `Setoran Pedagang Pantai (Pokok)`, `Bunga Pedagang Pantai`, `Pendapatan Bunga Bank`, `Pendapatan Desa Wisata`, `Pendapatan Fee Pupuk`, `Pelunasan Piutang Usaha`, `Pendapatan Lain-lain`
   - Keluar: `Biaya Pegawai`, `Biaya Transport`, `Biaya Kantor/ATK`, `Biaya Penyusutan`, `Pajak Bunga Bank`, `Administrasi Bank`, `Dana Sosial`, `BPJS Ketenagakerjaan`, `Pembayaran Hutang Usaha`, `Pencairan Pinjaman Pedagang`, `Setoran ke Bank`, `Biaya Lain-lain`
4. Setiap kategori **otomatis memetakan akun lawan** (Akun Lawan tidak perlu dipilih manual, cukup bisa diubah kalau perlu).

---

## 5. TUGAS E — "JURNAL TRANSAKSI" ≠ "DAFTAR TRANSAKSI"

Keduanya **berbeda** dan harus jadi dua menu terpisah:

1. **Daftar Transaksi** (sudah ada) = daftar **dokumen/nota**: nomor nota, tanggal, jenis (Penjualan/Pembelian/Kas/Angsuran), unit usaha, pihak, total, status (Draft/Diposting). Bisa dicari, difilter per tanggal & jenis, **dibuka, diedit, dihapus, dan dicetak nota**.
2. **Jurnal Umum** (buat baru) = hasil **akuntansi double-entry** dari dokumen tadi: tanggal, nomor bukti, akun, uraian, **Debet**, **Kredit**. Bersifat *read-only* (tidak bisa diedit langsung — kalau salah, perbaiki dokumennya lalu jurnal ikut berubah). Total debet wajib sama dengan total kredit; tampilkan indikator seimbang/tidak.
3. Tambahkan menu laporan yang menurunkan dari Jurnal Umum (format mengikuti laporan Excel BUMDes yang sekarang):
   - **Buku Besar** per akun
   - **Neraca Saldo** (Saldo Awal – Jurnal – Saldo Akhir)
   - **Laba Rugi** (Pendapatan: Jasa Konsumsi / Perdagangan / ATK / Pendapatan Lain; Pembelian & Persediaan; Biaya Operasional & Non Operasional; Pajak)
   - **Neraca / Laporan Posisi Keuangan**
   - **Arus Kas**, **Buku Kas**, **Buku Bank**
   - **Laporan Persediaan (Kartu Stok)** dan **Laporan Piutang / Hutang**
   - Semua laporan bisa difilter per bulan & per unit usaha, dan bisa **export Excel + PDF**.
4. Beri tombol **Posting** pada dokumen: Draft belum masuk jurnal, setelah diposting baru membentuk jurnal dan mengunci dokumen (ada tombol Batal Posting untuk admin).

---

## 6. TUGAS F — ATURAN JURNAL OTOMATIS (WAJIB BENAR)

Gunakan nomor akun yang sudah dipakai BUMDes:
`1.1.01.01 Kas Tunai`, `1.1.01.02 Kas di Bank BPD`, `1.1.03.01 Piutang Usaha`, `1.1.03.03 Piutang Pedagang Pantai`, `1.1.05.01 Persediaan Barang Dagang`, `1.3.03.01 Inventaris Kantor`, `1.3.07.01 Akm Penyusutan Inventaris`, `2.1.00.01 Utang Usaha`, `2.1.00.02 Utang Gaji`, `3.1.01.00 Modal Desa Munggu`, `3.2.10.00 Dana Sosial`, `4.1.1 Penjualan Jasa Konsumsi`, `4.1.2 Penjualan Perdagangan`, `4.1.3 Penjualan ATK`, `4.4.1 Pendapatan Bunga Bank`, `4.4.2 Pendapatan Desa Wisata`, `4.4.3 Pendapatan Fee Pupuk`, `4.4.4 Pendapatan Bunga Pedagang Pantai`, `5.1.1 Pembelian Konsumsi`, `5.1.2 Pembelian Dagang`, `5.1.3 Pembelian ATK`, `6.1.1 Biaya Pegawai`, `6.1.2 Biaya Transport`, `6.1.3 Biaya Kantor`, `6.1.4 Biaya Penyusutan`, `6.2.1 Pajak Bunga Bank`, `6.2.2 Administrasi Bank`, `8.0 Pajak`.

Aturan:

- Penjualan tunai: **D** Kas/Bank — **K** Penjualan (sesuai kategori produk: konsumsi/perdagangan/ATK)
- Penjualan kredit: **D** Piutang Usaha — **K** Penjualan
- Pelunasan piutang: **D** Kas/Bank — **K** Piutang Usaha
- Pembelian tunai: **D** Pembelian (sesuai kategori) — **K** Kas/Bank
- Pembelian kredit: **D** Pembelian — **K** Utang Usaha
- Pembayaran hutang: **D** Utang Usaha — **K** Kas/Bank
- Pencairan pinjaman pedagang pantai: **D** Piutang Pedagang Pantai — **K** Kas/Bank
- Angsuran pedagang pantai: **D** Kas/Bank — **K** Piutang Pedagang Pantai (pokok) dan **K** Pendapatan Bunga Pedagang Pantai (bunga)
- Bunga bank: **D** Bank — **K** Pendapatan Bunga Bank; pajak & admin bank: **D** Biaya — **K** Bank
- Penambahan kas dari bank: **D** Kas Tunai — **K** Kas di Bank
- Biaya operasional: **D** Biaya terkait — **K** Kas/Bank
- Dana sosial: **D** Dana Sosial — **K** Kas/Bank

Tambahkan **unit test** untuk tiap aturan di atas: simpan 1 transaksi contoh, cek jurnal yang terbentuk seimbang dan akunnya benar.

---

## 7. TUGAS G — UX: JANGAN RELOAD, JANGAN LOMPAT KE ATAS

Ini keluhan paling mengganggu: setiap kali klik tombol/dropdown, halaman **refresh dan posisi scroll balik ke atas**, sehingga input panjang jadi susah.

Perbaiki dengan:

1. Semua aksi di dalam form (pilih produk, tambah baris, hapus baris, hitung total, ganti metode/unit usaha) dikerjakan **tanpa reload halaman** — pakai Livewire (`wire:model.live`, `wire:click.prevent`) atau Alpine.js + fetch/AJAX. Jangan pakai submit form biasa untuk hal-hal kecil ini.
2. **Posisi scroll dan fokus kursor dipertahankan** setelah setiap aksi. Jangan ada `window.scrollTo(0,0)` atau redirect ke halaman yang sama.
3. Tombol dan link di dalam form diberi `type="button"` / `preventDefault()` supaya tidak ikut men-submit form.
4. Setelah simpan: tampilkan **toast/notifikasi hijau** ("Transaksi tersimpan — No. Nota 939") dan **form langsung kosong siap input berikutnya**, bukan pindah halaman. Sediakan tombol "Lihat Nota" dan "Cetak".
5. Dropdown autocomplete tidak boleh tertutup waktu diklik, dan daftar bisa di-scroll.
6. Navigasi menu sidebar pakai `wire:navigate` (SPA-like) supaya tidak full reload, dan **menu yang aktif tetap tersorot**.
7. **Autosave draft** di localStorage: kalau browser tertutup tidak sengaja, isi form kembali saat dibuka lagi.
8. Tambahkan pintasan keyboard: `Enter` = pindah ke field berikutnya, `Ctrl+Enter` = simpan, `Alt+N` = tambah baris.
9. Pastikan tampilan tetap rapi di layar kecil (laptop admin) dan angka rupiah diformat `Rp 1.750.000` saat ditampilkan, tapi tetap angka polos saat diinput.

---

## 8. CARA KERJA YANG SAYA MINTA

1. Baca dulu struktur proyek (routes, model, migration, view) sebelum mengubah apa pun.
2. Kerjakan berurutan: **A → B → C → D → E → F → G**, dan setiap tugas selesai beri tahu saya file apa saja yang diubah.
3. Buat migration baru (jangan edit migration lama yang sudah jalan), lalu `php artisan migrate` + jalankan seeder.
4. Jangan hapus data yang sudah ada.
5. Setelah selesai, kasih saya: daftar perintah yang harus saya jalankan, dan checklist pengujian manual (input 1 penjualan tunai, 1 penjualan kredit, 1 pembelian kredit, 1 kas keluar — lalu cek Jurnal Umum seimbang).

---

## LAMPIRAN A — DAFTAR PRODUK BUMDes (dari laporan keuangan Juni 2026)

Kolom: Nama | Satuan | Stok akhir Juni 2026 | Harga Beli | Harga Jual | Sumber harga jual

| Nama | Satuan | Stok | Harga Beli | Harga Jual | Kategori | Sumber Harga Jual |
|---|---|---|---|---|---|---|
| Hvs 70 gr F4 sidu | Rim | 8 | 51000 | 55000 | atk | excel (kartu stock) |
| HVS 80 gr A4 SIDU | - | 5 | 53000 | 58500 | atk | estimasi +10% (cek manual) |
| Hvs 70 gr F4 Peper one | - | 16 | 48000 | 53000 | atk | estimasi +10% (cek manual) |
| Hvs 70 gr F4 E Peper | - | 5 | 46800 | 55000 | atk | excel (kartu stock) |
| Amplop Paper Line No 90 | - | 4 | 19000 | 21000 | atk | estimasi +10% (cek manual) |
| Amplop Paper Line No 110 | - | 2 | 18500 | 20500 | atk | estimasi +10% (cek manual) |
| Map Big sneil biru | - | 8 | 6500 | 7000 | atk | estimasi +10% (cek manual) |
| Map kancing BIG | - | 61 | 2000 | 2000 | atk | estimasi +10% (cek manual) |
| Businis File Merah | - | 20 | 1800 | 2000 | atk | estimasi +10% (cek manual) |
| Businis File L4 Big Transparan | - | 20 | 1300 | 1500 | atk | estimasi +10% (cek manual) |
| Buku Gambar | - | 140 | 3400 | 3500 | atk | estimasi +10% (cek manual) |
| Bendera Merah Putih Uk 90*60 | - | 5 | 22500 | 25000 | atk | estimasi +10% (cek manual) |
| Calkulator | - | 1 | 50000 | 55000 | atk | estimasi +10% (cek manual) |
| Stop Map sneil | - | 10 | 540 | 500 | atk | estimasi +10% (cek manual) |
| Stop Map Polio M | - | 2 | 460 | 500 | atk | estimasi +10% (cek manual) |
| Stop Map Folio | - | 235 | 500 | 500 | atk | estimasi +10% (cek manual) |
| Stop Map Biola | - | 50 | 1500 | 1500 | atk | estimasi +10% (cek manual) |
| stop Map seagull Jepit | - | 14 | 8300 | 9000 | atk | estimasi +10% (cek manual) |
| Stop Map Zipper Resleting Big | - | 4 | 8000 | 9000 | atk | estimasi +10% (cek manual) |
| Bantek TK 1401 Karton | - | 10 | 15000 | 16500 | atk | estimasi +10% (cek manual) |
| Bantek TK 1465 Hitam | - | 4 | 30000 | 33000 | atk | estimasi +10% (cek manual) |
| Bolpoin paster/Bolpoin Queen | - | 5 | 1000 | 1000 | atk | estimasi +10% (cek manual) |
| Bolpoin Joyko BP 342 VOCUS HTM | - | 36 | 1250 | 1500 | atk | estimasi +10% (cek manual) |
| Bolpoin Joyko GP 330 Ht 0.5mm | - | 36 | 1250 | 1500 | atk | estimasi +10% (cek manual) |
| Bolpoin Joyko GP 265 Ht | - | 35 | 2100 | 2500 | atk | estimasi +10% (cek manual) |
| Bantalan Stempel No 1 | - | 2 | 6200 | 7000 | atk | estimasi +10% (cek manual) |
| Bantalan Stempel No 2 | - | 0 | 13000 | 14500 | atk | estimasi +10% (cek manual) |
| BoarMaker  Hitam Non Permanen | - | 29 | 6750 | 7500 | atk | estimasi +10% (cek manual) |
| BoarMaker  Merah | - | 7 | 6250 | 7000 | atk | estimasi +10% (cek manual) |
| BoarMaker  Biru | - | 9 | 6250 | 7000 | atk | estimasi +10% (cek manual) |
| Box File Marmer Tk | - | 1 | 12900 | 14000 | atk | estimasi +10% (cek manual) |
| B Snowman Marker Permanen | - | 34 | 6000 | 6500 | atk | estimasi +10% (cek manual) |
| Buku Ekspedisi | - | 2 | 12000 | 13000 | atk | estimasi +10% (cek manual) |
| Buku Bklock Note kw | - | 29 | 2000 | 2000 | atk | estimasi +10% (cek manual) |
| Buku Bklock Note | - | 0 | 3800 | 4000 | atk | estimasi +10% (cek manual) |
| Tipe EX Kenko | - | 6 | 4000 | 4500 | atk | estimasi +10% (cek manual) |
| Binder clip No 260 | - | 7 | 14000 | 15500 | atk | estimasi +10% (cek manual) |
| Binder clip No 200 | - | 8 | 8950 | 10000 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Binder clip No 155 | - | 3 | 5300 | 6000 | atk | estimasi +10% (cek manual) |
| Binder clip No 111 | - | 5 | 3800 | 4000 | atk | estimasi +10% (cek manual) |
| Klip kecil No 3 | - | 10 | 1550 | 1500 | atk | estimasi +10% (cek manual) |
| Klip kecil No 5 | - | 13 | 4250 | 4500 | atk | estimasi +10% (cek manual) |
| Klip kecil No 5 Warna Deli | - | 5 | 4250 | 4500 | atk | estimasi +10% (cek manual) |
| Buku Kwarto | - | 2 | 9500 | 10500 | atk | estimasi +10% (cek manual) |
| Buku Folio 50 lbr | - | 3 | 9500 | 10500 | atk | estimasi +10% (cek manual) |
| Buku Folio paperline | - | 14 | 16000 | 17500 | atk | estimasi +10% (cek manual) |
| Buku Kas | - | 6 | 9800 | 11000 | atk | estimasi +10% (cek manual) |
| Buku Folio Kas | - | 3 | 16000 | 17500 | atk | estimasi +10% (cek manual) |
| Batre Alkalin AA | - | 3 | 27800 | 30500 | atk | estimasi +10% (cek manual) |
| Batre Alkalin AAA | - | 8 | 26500 | 29000 | atk | estimasi +10% (cek manual) |
| Batre Energizer Alkaline 9V | - | 7 | 32000 | 35000 | atk | estimasi +10% (cek manual) |
| Lem Stick Joyko | - | 5 | 3000 | 3500 | atk | estimasi +10% (cek manual) |
| Gunting | - | 1 | 9300 | 10000 | atk | estimasi +10% (cek manual) |
| Isi Steples MAX | - | 15 | 2100 | 2500 | atk | estimasi +10% (cek manual) |
| Isi Staples Honaga | - | 4 | 10000 | 11000 | atk | estimasi +10% (cek manual) |
| Staples | - | 3 | 16000 | 17500 | atk | estimasi +10% (cek manual) |
| Staples SDI | - | 4 | 22500 | 25000 | atk | estimasi +10% (cek manual) |
| Papan White Board | - | 1 | 45000 | 49500 | atk | estimasi +10% (cek manual) |
| Pelubang Kertas | - | 1 | 12500 | 14000 | atk | estimasi +10% (cek manual) |
| Post it | - | 7 | 6000 | 6500 | atk | estimasi +10% (cek manual) |
| Post it big 656 hijau | - | 2 | 4000 | 4500 | atk | estimasi +10% (cek manual) |
| Post it big 656 Pink | - | 2 | 4000 | 4500 | atk | estimasi +10% (cek manual) |
| Post It Poronto | - | 9 | 8350 | 9000 | atk | estimasi +10% (cek manual) |
| Kwitansi | - | 0 | 2900 | 4000 | atk | excel (kartu stock) |
| Pensil 2B | - | 21 | 1500 | 1500 | atk | estimasi +10% (cek manual) |
| Penghapus | - | 7 | 650 | 500 | atk | estimasi +10% (cek manual) |
| Penggaris | - | 3 | 1850 | 2000 | atk | estimasi +10% (cek manual) |
| Stiker Folio | - | 100 | 7500 | 8000 | atk | estimasi +10% (cek manual) |
| Tinta Epson T664 Black | - | 2 | 83000 | 91500 | atk | estimasi +10% (cek manual) |
| Tinta Epson T664 Warna | - | 3 | 88000 | 97000 | atk | estimasi +10% (cek manual) |
| Tinta Epson 003 Black | - | 8 | 83000 | 91500 | atk | estimasi +10% (cek manual) |
| Tinta Epson 003 Warna | - | 8 | 83000 | 91500 | atk | estimasi +10% (cek manual) |
| Tinta Epson 001 Warna | - | 9 | 117000 | 128500 | atk | estimasi +10% (cek manual) |
| Tinta Epson 001Hitam | - | 4 | 177000 | 194500 | atk | estimasi +10% (cek manual) |
| Tinta Brother | - | 5 | 97000 | 106500 | atk | estimasi +10% (cek manual) |
| Tinta Stempel Yamura | - | 2 | 6000 | 6500 | atk | estimasi +10% (cek manual) |
| Tinta Stempel Spass | - | 4 | 49000 | 54000 | atk | estimasi +10% (cek manual) |
| Zipper Bag | - | 0 | 10500 | 12000 | atk | excel (kartu stock) |
| Baliho 200*150 | - | 0 | 325000 | 370000 | jasa | excel (kartu stock) |
| Spanduk 85*250 | - | 0 | 75000 | 85000 | jasa | excel (kartu stock) |
| Spanduk 300*100 | - | 0 | 75000 | 120000 | jasa | excel (kartu stock) |
| Jilid Buku | - | 0 | 65000 | 70000 | jasa | excel (kartu stock) |
| Jiilid Cover | - | 0 | 10000 | 15000 | perdagangan | excel (kartu stock) |
| Fotocopy | Lbr | 0 | 250 | 300 | jasa | excel (kartu stock) |
| OLI ATAS (CASTROL) | - | 5 | 40500 | 44500 | perdagangan | estimasi +10% (cek manual) |
| Tali Rabas | - | 6 | 151667 | 167000 | perdagangan | estimasi +10% (cek manual) |
| Piringan RabbasTanika | - | 26 | 46000 | 50500 | perdagangan | estimasi +10% (cek manual) |
| Kopi Setia Bali 200gr | - | 0 | 25000 | 27500 | konsumsi | estimasi +10% (cek manual) |
| Gula pasir ( Gulaku) | Pcs | 0 | 17500 | 19000 | konsumsi | excel (kartu stock) |
| Snack Ringan | - | 0 | 87000 | 96000 | konsumsi | excel (kartu stock) |
| Sariwangi | - | 0 | 12000 | 13000 | konsumsi | estimasi +10% (cek manual) |
| Aqua 1500 ml | Dus | 0 | 50000 | 52000 | konsumsi | excel (kartu stock) |
| Aqua 600 ml | - | 10 | 44000 | 47000 | konsumsi | excel (kartu stock) |
| Aqua 220ml cube | - | 87 | 22500 | 25000 | konsumsi | estimasi +10% (cek manual) |
| Aqua 330 ml | - | 20 | 35000 | 38000 | konsumsi | excel (kartu stock) |
| Le Mineral | - | 0 | 24000 | 26500 | konsumsi | estimasi +10% (cek manual) |
| Snac | - | 0 | 147399 | 162000 | konsumsi | estimasi +10% (cek manual) |
| Pakaet Sembako | - | 0 | 7964500 | 8761000 | konsumsi | estimasi +10% (cek manual) |
| Pohon Manggis | - | 0 | 80000 | 88000 | perdagangan | estimasi +10% (cek manual) |
| Baju Kemeja Desa Wisata | - | 0 | 325000 | 357500 | perdagangan | estimasi +10% (cek manual) |
| Tisu Jolly Kecil | - | 0 | 3000 | 4000 | konsumsi | excel (kartu stock) |
| Bola Chusher Uk 5 | - | 10 | 143200 | 157500 | perdagangan | estimasi +10% (cek manual) |
| Bola Chusher Uk 4 | - | 6 | 135200 | 148500 | perdagangan | estimasi +10% (cek manual) |
| Baju Endek SD | - | 2 | 135000 | 148500 | perdagangan | estimasi +10% (cek manual) |
| Topi | - | 1 | 50000 | 50000 | perdagangan | excel (kartu stock) |
| Baju Polo | - | 1 | 100000 | 176000 | perdagangan | excel (kartu stock) |
| Training | - | 1 | 90000 | 99000 | perdagangan | estimasi +10% (cek manual) |
| Pin Posyandu | - | 22 | 15900 | 17500 | perdagangan | estimasi +10% (cek manual) |
| Pakaian Pregina Thp 1 | - | 1 | 8925000 | 9817500 | perdagangan | estimasi +10% (cek manual) |
| Pakaian Pregina Thp 2 | - | 1 | 28475000 | 31322500 | perdagangan | estimasi +10% (cek manual) |
| Kain Endek | - | 1 | 10300000 | 11330000 | perdagangan | estimasi +10% (cek manual) |
| Topi Bulan Bakti Gotong Royong | - | 0 | 40000 | 44000 | perdagangan | estimasi +10% (cek manual) |
| Baju Jersy Bulan Bakti | - | 0 | 160000 | 176000 | perdagangan | estimasi +10% (cek manual) |
| Celana Bulan Bakti | - | 0 | 110000 | 121000 | perdagangan | estimasi +10% (cek manual) |
| Pakaian Endek LPM | - | 0 | 425000 | 467500 | perdagangan | estimasi +10% (cek manual) |
| Pejati | - | 0 | 72000 | 75000 | upakara | excel (kartu stock) |
| Sepatu Lomba Senam | - | 0 | 229000 | 253000 | perdagangan | excel (kartu stock) |
| Baju Jersy Lomba Senam | - | 0 | 160000 | 176000 | perdagangan | excel (kartu stock) |
| Celana Lomba Senam | - | 0 | 110000 | 121000 | perdagangan | excel (kartu stock) |
| Topi Lomba Senam | - | 0 | 45000 | 50000 | perdagangan | excel (kartu stock) |
| Motivasi STT | - | 0 | 4750000 | 5225000 | upakara | estimasi +10% (cek manual) |
| Banten Dewa Yadnya | - | 0 | 1900000 | 2000000 | upakara | excel (kartu stock) |
| Banten Tumpek | - | 0 | 950000 | 10000000 | upakara | excel (kartu stock) |
| Banten Pitra Yadnya | - | 0 | 9500000 | 5000000 | upakara | excel (kartu stock) |
| PUPUK | - | 0 | 179900000 | 179900000 | pupuk | excel (kartu stock) |
| PMT | Bks | 0 | 11000 | 12000 | konsumsi | excel (kartu stock) |
| Nasi Bungkus | Bks | 0 | 15000 | 17500 | konsumsi | excel (kartu stock) |
| Snack bungkus | Ktk | 0 | 4000 | 5000 | konsumsi | excel (kartu stock) |
| Snack Kotak | - | 0 | 6000 | 7000 | konsumsi | excel (kartu stock) |
| Nasi Kotak | - | 0 | 23000 | 25000 | konsumsi | excel (kartu stock) |
| Kopi Setia Bali | - | 0 | 25000 | 27000 | konsumsi | excel (kartu stock) |
| Permen Campur | - | 0 | 23500 | 26000 | konsumsi | excel (kartu stock) |
| Le mineral Galon | - | 0 | 24000 | 25000 | konsumsi | excel (kartu stock) |
| Aqua 220 ml cebol | - | 0 | 22500 | 24000 | konsumsi | excel (kartu stock) |
| Ruput Sintetis | - | 0 | 0 | 0 | perdagangan | estimasi +10% (cek manual) |
| Celana Training | - | 0 | 110000 | 121000 | perdagangan | excel (kartu stock) |
| Baju Endek | - | 0 | 425000 | 468000 | perdagangan | excel (kartu stock) |
| Motivasi Hut STT | - | 0 | 4750000 | 5000000 | upakara | excel (kartu stock) |
| Hvs 70 gr F4 Paper One | Rim | 0 | 48000 | 55000 | atk | excel (kartu stock) |
| Amplop 90 pps | - | 0 | 19000 | 25000 | atk | excel (kartu stock) |
| Amplop Paper Line 110 | - | 0 | 20000 | 23000 | atk | excel (kartu stock) |
| Bantalan Stempel | - | 0 | 13000 | 15000 | atk | excel (kartu stock) |
| Batre AA | - | 0 | 27800 | 35000 | atk | excel (kartu stock) |
| Buku Block note | - | 0 | 3800 | 4000 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Bolpoin Joyko 330 Ht | - | 0 | 1250 | 1500 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Bolpoin Joyko 342 Vocus Htm | - | 0 | 1250 | 2000 | atk | excel (kartu stock) |
| Bolpoin Joyko 365 Ht | - | 0 | 2500 | 3500 | atk | excel (kartu stock) |
| Boarmarker Hitam | - | 0 | 6750 | 7500 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Pensil 4 B | - | 0 | 4500 | 5000 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Penghapus Pensil | - | 0 | 2000 | 2000 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Penghapus Papan | - | 0 | 6500 | 7000 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Kalkulator | - | 0 | 50000 | 55000 | atk | excel (kartu stock) |
| Paper Klip No 3 | - | 0 | 1550 | 1500 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Paper Klip No 5 | - | 0 | 4250 | 4500 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Map Folio | - | 0 | 500 | 500 | atk | estimasi +10% (angka excel janggal, cek manual) |
| Tinta 0001 Warna | - | 0 | 117000 | 128500 | atk | estimasi +10% (cek manual) |
| Tinta 0001 hitam | - | 0 | 177000 | 190000 | atk | excel (kartu stock) |
| Tinta 0003 Hitam | - | 0 | 83000 | 110000 | atk | excel (kartu stock) |
| Tinta Stempel | - | 0 | 6000 | 8000 | atk | excel (kartu stock) |
