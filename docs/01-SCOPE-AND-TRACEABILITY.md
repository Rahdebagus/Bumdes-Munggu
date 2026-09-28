# 01 — Ruang lingkup dan traceability

## Tujuan dan pengguna

Bangun SIA BUMDes Munggu yang menghubungkan usaha dagang, pinjaman, persediaan, aset, jurnal, audit internal, dan pelaporan. Sistem ditujukan untuk Admin/Pengelola dan Pimpinan, bukan portal masyarakat atau nasabah.

PDF terbaru menyebut data persediaan Juni 2026 sebanyak 144 baris yang mewakili sekitar 125 nama barang dan 143 transaksi penjualan (h.1). Angka tersebut konteks penelitian, bukan data produksi yang boleh dibuat-buat. Jangan seed identitas atau transaksi nyata dari dugaan.

## Kebutuhan dan asalnya

| ID | Kebutuhan | Rujukan PDF | Modul / kriteria |
|---|---|---|---|
| REQ-01 | Login, peran Admin/Pimpinan, nonaktifkan akun | h.41–47, 48, 112–113 | Auth; endpoint terlindungi policy |
| REQ-02 | Dashboard periode dan unit | h.42, 48, 92 | Dashboard; semua KPI dari backend |
| REQ-03 | Unit usaha, akun, pihak, produk | h.46, 48–49, 77–80 | Master; nonaktif bukan hapus riwayat |
| REQ-04 | Saldo awal, import Excel, lock seimbang | h.49, 79, 113 | OpeningBalance; preview sebelum commit |
| REQ-05 | Penjualan/pembelian tunai dan kredit | h.40, 49–50, 53–54 | Trading; jurnal, HPP, stok atomik |
| REQ-06 | Pemasukan/pengeluaran, bukti, akun lawan | h.40–41, 50, 54–55 | Cash; hindari pencatatan pendapatan ganda |
| REQ-07 | Pinjaman, pencairan, jadwal, angsuran | h.46–47, 50, 55, 82–84 | Lending; pisah pokok, bunga, denda |
| REQ-08 | Stok/kartu stok/opname + approval | h.46, 51, 60, 87–88 | Inventory; tidak ada edit stok langsung |
| REQ-09 | Aset, penyusutan garis lurus | h.47, 51, 55–56, 84 | FixedAssets; cegah duplikasi |
| REQ-10 | Jurnal, buku besar, draft/posted/reversed | h.51, 53–57, 81–82 | Accounting; trace ke transaksi |
| REQ-11 | Periode terbuka/ditutup, buka kembali | h.52, 78 | Periods; Pimpinan close/reopen |
| REQ-12 | Audit internal dan rekonsiliasi | h.51–52, 60 | Reconciliation; PASS/WARNING/FAIL |
| REQ-13 | Laba rugi, neraca, perubahan modal, arus kas | h.56–59, 106–109 | Reporting; PDF/Excel konsisten |
| REQ-14 | Laporan kredit pinjaman | h.59, 110 | Reporting; aging dan sisa pokok |
| REQ-15 | Omzet desa, approval, kirim, terima | h.52, 59, 85–86, 111 | VillageReport; snapshot immutable |
| REQ-16 | Bukti, verifikasi, histori | h.52, 69, 85–86 | Evidence/AuditLog; akses privat |
| REQ-17 | Responsif dan keamanan password | h.47–48 | UI/Auth; hash password |
| REQ-18 | Black box, UAT dan SUS | h.112–124; BAB II SUS | QA; catat hasil aktual |

## Matriks kewenangan

Lihat berarti baca detail sesuai modul, tanpa memperoleh endpoint mutasi. Semua pemeriksaan di server.

| Aktivitas | Admin | Pimpinan |
|---|---|---|
| Dashboard, jurnal, buku besar, enam laporan, unduh | Ya | Ya |
| Kelola unit, akun, pihak, produk | Ya | Baca |
| Buat/edit draft, post, reverse transaksi | Ya | Baca |
| Input/lock saldo awal | Ya | Baca |
| Buat/input/ajukan opname | Ya | Baca |
| Setujui/minta revisi opname | Tidak | Ya |
| Post opname yang disetujui | Ya | Tidak |
| Input aset, post penyusutan | Ya | Baca |
| Buat/buka periode baru | Ya | Ya |
| Tutup/buka kembali periode dengan alasan | Tidak | Ya |
| Susun/ajukan/kirim/catat penerimaan laporan desa | Ya | Baca |
| Setujui/minta revisi laporan desa | Tidak | Ya |
| Unggah bukti transaksi | Ya | Tidak |
| Verifikasi bukti | Bukan pengunggah sendiri [TEKNIS] | Ya |
| Lihat audit/rekonsiliasi | Ya | Ya |
| Kelola akun pengguna | CLI pengelola sistem [TEKNIS] | Tidak |

Jika hanya ada satu Admin, Pimpinan memverifikasi bukti. Disable registrasi publik. Pengelolaan akun via CLI cukup untuk versi pertama agar tidak menciptakan role baru di luar PDF.

## Cakupan yang tidak ditambahkan

Tidak ada marketplace, pembayaran online, login pelanggan, mobile native, multi-BUMDes, HR/payroll, integrasi bank otomatis, atau modul tabungan nasabah. PDF menyebut usaha kredit; tidak tersedia model simpanan/tabungan yang memadai untuk mengimplementasikan produk tabungan.

Retur komersial parsial, pelepasan aset, migrasi pinjaman berjalan yang kompleks, dan tarif pajak otomatis tidak boleh diasumsikan sudah tersedia. Versi pertama memberi reverse transaksi penuh dan input pajak eksplisit dengan mapping yang disetujui. Kebutuhan tambahan dicatat sebagai perubahan scope.

## Konflik yang diselesaikan untuk blueprint

- BAB I h.3 membatasi pengguna pada Admin, tetapi BAB III merinci Pimpinan. Gunakan dua role sesuai BAB III.
- BAB III terbaru memuat pinjaman dan angsuran; jangan menggantinya dengan piutang penjualan.
- Gambar PDM dan kamus tabel memiliki variasi penamaan. Gunakan kamus h.77–88 untuk field, bukan menyalin label gambar yang berbeda.
- Tidak ada tabel jurnal/buku_besar/modal/persediaan tersendiri pada 18 tabel inti. Jurnal berasal dari transaksi_detail; buku besar/laporan berupa query.
- `kategori_transaksi` disebut naratif tetapi tidak ada pada daftar 18 tabel. Versi pertama memakai kode kategori dan konfigurasi mapping terkontrol, dijelaskan dokumen 05.
