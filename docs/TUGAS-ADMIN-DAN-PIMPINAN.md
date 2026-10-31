# Tugas Admin dan Pimpinan — SIA BUMDes Munggu

Panduan singkat pembagian kerja di aplikasi SIA BUMDes Munggu (BUM Desa "Tri Guna Sejahtera").
Aplikasi punya dua peran:

| Peran | Singkatnya |
|---|---|
| **Admin** (pengelola/operator) | **Mencatat**: semua transaksi, data master, saldo awal, stok opname, aset, laporan desa. |
| **Pimpinan** (direktur/pengawas) | **Memeriksa & menyetujui**: melihat semua laporan, menyetujui stok opname & laporan omzet desa, memverifikasi bukti. Tidak mencatat transaksi. |

Prinsipnya: **yang mencatat bukan yang menyetujui.** Karena itu menu input (Penjualan,
Pembelian, Kas, Pelunasan, Angsuran, Jurnal Penyesuaian) tidak tampil di akun Pimpinan.

---

## 1. Menu yang terlihat per peran

| Menu | Admin | Pimpinan |
|---|:-:|:-:|
| Dashboard | ✔ (+ tombol Tambah Transaksi) | ✔ lihat |
| Penjualan, Pembelian, Pemasukan & Pengeluaran | ✔ | — |
| Pelunasan Hutang / Piutang Usaha | ✔ | — |
| Pembayaran Angsuran | ✔ | — |
| Daftar Transaksi | ✔ edit / hapus draft, posting, batal posting, cetak | ✔ lihat & cetak |
| Data Peminjam, Data Pinjaman, Jadwal Angsuran | ✔ kelola | ✔ lihat |
| Unit Usaha, Akun, Pihak, Produk | ✔ kelola | ✔ lihat |
| Saldo Awal | ✔ isi & kunci | ✔ lihat |
| Stok Barang | ✔ | ✔ lihat |
| Stok Opname | ✔ buat, hitung, ajukan, posting | ✔ **setujui / minta revisi** |
| Jurnal Umum, Buku Besar, Neraca Saldo | ✔ lihat | ✔ lihat |
| Jurnal Penyesuaian | ✔ | — |
| Periode Akuntansi | ✔ buat, tutup, buka kembali | ✔ lihat |
| Aset Tetap & Penyusutan | ✔ daftar aset, posting penyusutan | ✔ lihat |
| Laporan Keuangan (Laba Rugi, Neraca, Arus Kas, Buku Kas/Bank, Persediaan, Piutang & Hutang, dll.) | ✔ lihat & unduh | ✔ lihat & unduh |
| Laporan Omzet Desa | ✔ susun, ajukan, buat revisi, kirim | ✔ **setujui** |
| Bukti Transaksi | ✔ unggah | ✔ **verifikasi / tolak** |
| Histori Aktivitas | ✔ lihat | ✔ lihat |

---

## 2. Tugas Admin

### Sekali di awal (sebelum mulai)
1. **Data Master → Unit Usaha**: cek nama unit & penanggung jawab.
2. **Data Master → Data Pihak**: masukkan pelanggan, pemasok, dan peminjam (pedagang pantai).
3. **Data Master → Data Produk**: periksa produk; koreksi harga jual yang berlabel
   **perlu verifikasi** (harga perkiraan, belum harga asli). Klik harga → ketik → Enter.
4. **Periode Akuntansi**: buat periode bulan mulai (cutover).
5. **Saldo Awal**: unduh format Excel, isi dari Neraca terakhir, unggah → Preview → Simpan
   → pastikan *Debit = Kredit* hijau → **Kunci Saldo Awal**.

### Setiap hari
| Kejadian | Menu |
|---|---|
| Menjual barang/jasa (nasi, snack, ATK, fotocopy, upakara, pupuk) | **Penjualan** |
| Membeli barang dagangan dari pemasok | **Pembelian** (tunai, atau *kredit* bila dibayar belakangan) |
| Uang masuk/keluar bukan jual-beli (gaji, transport, bunga/admin bank, tarik kas dari bank, Desa Wisata, fee pupuk, dana sosial) | **Pemasukan & Pengeluaran** |
| Pedagang pantai membayar angsuran | **Pembayaran Angsuran** |
| Ada nota / kuitansi | buka transaksinya di **Daftar Transaksi** → unggah **Bukti** |

Tips form: ketik nama produk lalu pilih dengan ↑ ↓ + Enter, harga terisi otomatis.
`Ctrl+Enter` = Simpan & Posting, `Alt+N` = tambah baris. Isian tersimpan otomatis bila browser tertutup.

### Setiap tanggal 10 / 20 / akhir bulan
- **Pelunasan Hutang Usaha**: centang nota pembelian kredit yang dibayar (borongan) → Bayar & Posting.
- **Pelunasan Piutang Usaha**: catat pelanggan yang melunasi.

### Akhir bulan
1. **Daftar Transaksi** → filter status *Draft*: posting atau hapus draft yang tertinggal.
2. **Penyusutan Aset**: posting penyusutan bulan berjalan.
3. **Stok Opname**: hitung fisik barang → input hasil → **ajukan** ke Pimpinan →
   setelah disetujui, **posting** selisihnya.
4. **Jurnal Umum**: pastikan badge **Seimbang**. **Neraca**: pastikan **Seimbang**.
5. **Laporan Omzet Desa**: susun laporan bulan itu → **ajukan** ke Pimpinan →
   setelah disetujui, **kirim** dan catat penerimaannya.
6. **Periode Akuntansi**: **tutup** periode bila semua di atas beres.

### Bila ada kesalahan input
- Masih **Draft** → *Edit* atau *Hapus* di Daftar Transaksi.
- Sudah **Diposting** → buka transaksinya → **Batal Posting** (tulis alasan) →
  pilih *Batalkan & edit ulang* → perbaiki → posting lagi. Jurnal lama tidak dihapus,
  sistem membuat jurnal pembalik sehingga jejaknya tetap ada.

---

## 3. Tugas Pimpinan

### Setiap minggu
- **Pengawasan → Bukti Transaksi**: periksa bukti yang diunggah Admin →
  **Verifikasi** bila sesuai, **Tolak** (dengan alasan) bila tidak.
- **Dashboard**: pantau kas & bank, penjualan, piutang, dan pinjaman yang terlambat.

### Akhir bulan
1. **Stok Opname**: buka opname yang *diajukan* → bandingkan stok sistem vs fisik →
   **Setujui** atau **Minta revisi**.
2. **Laporan Keuangan**: periksa minimal
   - **Laba Rugi** — pendapatan per unit, HPP, biaya, laba bersih;
   - **Neraca** — harus *Seimbang*;
   - **Buku Kas & Buku Bank** — cocokkan dengan uang di brankas dan rekening koran;
   - **Piutang & Hutang** — siapa yang belum bayar / BUMDes belum bayar;
   - **Laporan Kredit** — pinjaman pedagang pantai yang terlambat.
3. **Laporan Omzet Desa**: buka laporan yang *diajukan* → periksa → **Setujui**. Bila ada yang
   salah, jangan disetujui; minta Admin membuat **versi revisi** lalu mengajukannya lagi.
   Setelah disetujui laporan terkunci (resmi) dan bisa diunduh PDF/Excel.
4. **Histori Aktivitas**: bila perlu, lihat siapa mengubah apa (termasuk batal posting dan alasannya).

### Yang tidak dilakukan Pimpinan
Mencatat penjualan/pembelian/kas, mengubah data master, mengisi saldo awal, memposting.
Bila menemukan kesalahan, minta Admin memperbaikinya (draft → edit; terposting → Batal Posting).

---

## 4. Alur kerja bersama

```
Stok opname   : Admin buat & hitung → Admin ajukan → Pimpinan setujui/revisi → Admin posting
Laporan desa  : Admin susun → Admin ajukan → Pimpinan setujui (salah? Admin buat versi revisi & ajukan lagi) → Admin kirim & catat diterima
Bukti         : Admin unggah → Pimpinan verifikasi / tolak
Transaksi     : Admin input → Simpan & Posting → (salah?) Admin Batal Posting → edit → posting
Tutup bulan   : Admin selesaikan draft, penyusutan, opname, laporan desa → Admin tutup periode
```

## 5. Checklist tutup bulan

- [ ] Tidak ada transaksi berstatus Draft yang tertinggal
- [ ] Semua bukti sudah diverifikasi Pimpinan
- [ ] Penyusutan aset bulan ini sudah diposting
- [ ] Stok opname disetujui Pimpinan dan sudah diposting
- [ ] Jurnal Umum & Neraca menunjukkan **Seimbang**
- [ ] Buku Kas = uang di brankas; Buku Bank = rekening koran
- [ ] Laporan Omzet Desa disetujui Pimpinan dan dikirim
- [ ] Periode bulan ini ditutup Admin

---

## 6. Akun & login

- Akun pengguna dibuat oleh pengelola sistem lewat perintah `php artisan app:create-user`
  (pilih peran *admin* atau *pimpinan*). Tidak ada pendaftaran mandiri.
- Akun latihan (hanya data demo, bukan server produksi), password `demo12345`:
  `admin.demo@bumdes-munggu.test` (Admin) dan `pimpinan.demo@bumdes-munggu.test` (Pimpinan).
- Jangan berbagi akun: setiap tindakan tercatat atas nama pengguna di Histori Aktivitas.
