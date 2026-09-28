# 11 — Testing, fixture, dan penerimaan

Dasar black box PDF h.112–118; evaluasi pengguna h.118–124. Semua hasil awal berstatus BELUM DIUJI. Jangan mengarang PASS atau nilai SUS.

## Fixture A: alur finansial lintas modul

Dataset sintetis pengujian; bukan transaksi BUMDes nyata. Cutover 1 Januari 2026, dua unit uji (dagang dan kredit), semua pajak/diskon nol, metode rata-rata bergerak. Laporan expected di bawah adalah konsolidasi. Opening unit dagang: kas/modal 8.000.000; unit kredit: kas/modal 2.000.000. Langkah 7–8 milik unit kredit; langkah lainnya unit dagang. Ini menjaga validasi jenis unit dan keseimbangan per unit tanpa transfer internal fiktif. Gunakan decimal dan susunan berikut:

| Langkah | Transaksi | Hasil utama |
|---|---|---|
| 0 | Opening kas10.000.000; modal10.000.000 | Debit=Kredit10.000.000 |
| 1 | Beli tunai10 barang @100.000 | Kas9.000.000; stok10 nilai1.000.000 |
| 2 | Jual tunai2 @150.000 | Kas9.300.000; revenue300.000; HPP200.000; stok8 |
| 3 | Jual kredit3 @150.000 | Piutang450.000; revenue total750.000; HPP total500.000; stok5 |
| 4 | Pelunasan piutang200.000 | Kas9.500.000; piutang250.000; revenue tetap750.000 |
| 5 | Beli kredit4 @125.000 | Utang500.000; stok9 nilai1.000.000; average111.111,111111 |
| 6 | Bayar utang100.000 | Kas9.400.000; utang400.000 |
| 7 | Cair pinjaman1.200.000, flat_total12%, tenor12 | Kas8.200.000; piutang pinjaman1.200.000; total bunga144.000 |
| 8 | Bayar angsuran pokok100.000+bunga12.000 | Kas8.312.000; pinjaman1.100.000; pendapatan kredit12.000 |
| 9 | Beban tunai50.000 | Kas8.262.000; beban50.000 |
| 10 | Aset tunai1.200.000, residu0, umur12bulan | Kas7.062.000; aset bruto1.200.000 |
| 11 | Penyusutan satu bulan100.000 | Akumulasi100.000; beban tambah100.000 |

Expected akhir:

- Kas **7.062.000**; piutang usaha **250.000**; piutang pinjaman **1.100.000**; persediaan **1.000.000**; aset tetap neto **1.100.000**.
- Total aset **10.512.000**; utang **400.000**; modal awal **10.000.000**; laba **112.000**; ekuitas **10.112.000**.
- Laba =750.000+12.000−500.000−50.000−100.000 = **112.000**.
- Omzet desa =750.000+12.000 = **762.000**. Tidak termasuk200.000 pelunasan atau100.000 pokok angsuran.
- Arus operasi **−1.738.000**; investasi **−1.200.000**; pendanaan0; arus bersih **−2.938.000**; opening kas10.000.000 menjadi7.062.000.

Verifikasi angka sama pada dashboard, laporan, PDF, XLSX, buku besar, dan rekonsiliasi. Fixture harus berasal dari Actions aplikasi, bukan menulis total hasil ke tabel agar tes tampak lulus.

## Fixture B: reverse lintas periode

Beban100.000 posted30 Juni, reverse2 Juli. Laba Juni turun100.000; Juli naik100.000 terhadap baseline; kas Juni turun100.000 dan kembali pada Juli. Original berstatus reversed tetap mempunyai baris jurnal dan posted_at. Reverse kedua ditolak. Periode Juni ditutup tetap tidak berubah setelah pembalik Juli.

## Fixture C: pembayaran sebagian

Pokok1.000.000, tenor3, flat_total10% → total bunga100.000. Jadwal pokok333.333,33;333.333,33;333.333,34. Bunga33.333,33;33.333,33;33.333,34. Bayar pertama pokok100.000+bunga10.000; pembayaran kedua untuk jadwal sama tetap tersimpan sebagai row lain. Reverse pembayaran pertama saja memulihkan outstanding sesuai alokasinya. Tolak overpayment dan pembayaran jadwal pinjaman lain.

## Fixture D: stok dan race

Opening10 @10.000; beli10 @14.000; jual5 → stok15 nilai180.000. Dua request serentak menjual masing-masing10: hanya satu boleh berhasil. Permintaan kedua mendapat insufficient stock; tidak ada setengah jurnal. Reverse pembelian yang sudah diikuti penjualan ditolak REVERSE_DEPENDENCY. Opname dari snapshot lama setelah mutasi baru harus ditolak.

## Fixture E: penyusutan dan retry

Aset1.000.000, residu100.000, umur7bulan. Total penyusutan sampai akhir persis900.000, tidak melewati residu. Posting bulan sama dua request hanya satu efektif. Reverse lalu repost terkoreksi boleh, dengan seluruh histori tersimpan. Uji aset migrasi dengan akumulasi awal.

## Matriks pengujian

| ID | Skenario | Expected |
|---|---|---|
| T01 | Login salah/nonaktif, logout | Ditolak/session berakhir |
| T02 | Pimpinan memanggil saveDraft/post via Livewire langsung | 403; tidak ada row |
| T03 | Draft penjualan tersimpan | Semua saldo tetap |
| T04 | Post dua kali/refresh retry | Satu set jurnal/mutasi |
| T05 | Request key sama payload beda | Konflik domain; tanpa efek |
| T06 | Ubah property/public method transaksi posted | Ditolak |
| T07 | Exception setelah insert jurnal | Rollback semua efek |
| T08 | Akun nonaktif/salah jenis | Posting ditolak |
| T09 | Closed period vs posting bersamaan | Salah satu sesuai lock, tidak bocor |
| T10 | Reverse lintas periode | Fixture B tepat |
| T11 | Pembayaran parsial/reverse | Fixture C tepat |
| T12 | Stok negatif/konkurensi/opname stale | Fixture D tepat |
| T13 | Depreciation duplicate/cap | Fixture E tepat |
| T14 | Opening debit/kredit tidak seimbang | Lock ditolak |
| T15 | Opening akun cocok tetapi rincian beda | Lock ditolak |
| T16 | Import salah kolom/duplikat/angka lokal | Error per baris, tanpa partial commit |
| T17 | Filter periode/unit | Saldo historis dan konsolidasi benar |
| T18 | Snapshot approved lalu data berubah | Snapshot tetap; versi baru eksplisit |
| T19 | Approval sendiri/tanpa hak | Ditolak sesuai policy |
| T20 | Upload file executable/MIME palsu/>5MiB | Ditolak |
| T21 | Unduh bukti tanpa login/path traversal | Tidak membocorkan file |
| T22 | Export XLSX string formula | Teks tidak dieksekusi sebagai formula |
| T23 | Edit master stok langsung | Ditolak |
| T24 | Pagination/search SQL injection field | Allowlist dan binding aman |
| T25 | Viewport360/768/1280 + keyboard | Semua aksi dapat diakses |
| T26 | Restore backup terpisah | Ledger/file hash dan login valid |

Unit test kalkulator decimal/jadwal/penyusutan, feature test Actions+HTTP+policies+MariaDB, browser test alur utama. Unit tests saja tidak cukup untuk menguji lock MariaDB. Gunakan database `bumdes_munggu_test` pada MariaDB 10.4 yang sama. Jangan mengetes database production.

## UAT

Admin menjalankan master→opening→beli→jual→kredit→bukti→post→reverse→aset→opname→ekspor. Pimpinan menjalankan lihat dashboard→audit→approval opname→approval omzet→close/reopen. Catat ID skenario, langkah, expected, actual, pass/fail, catatan pengguna, severity, bukti, tester, tanggal, build SHA. Perbaiki gagal kritis dan ulangi skenario terdampak.

UAT fungsional lulus jika hasil bisnis sesuai. Skor kepuasan Likert dapat dicatat terpisah; tidak menggantikan bukti bahwa perhitungan benar.

## SUS

Gunakan 10 butir SUS yang dipertahankan konsisten sesuai instrumen penelitian, skala1–5. Skor item ganjil=response−1; genap=5−response; total×2,5 menghasilkan0–100. Jangan mencampurkan daftar skenario UAT menjadi butir SUS atau menyebut skor SUS sebagai persentase keberhasilan. Masukkan butir dan terjemahan tervalidasi dari instrumen yang dipakai peneliti; jangan membuat klaim hasil sebelum responden mengisi.

## Definition of Done global

Semua fitur wajib berfungsi pada data MariaDB, fixture keuangan cocok, critical/high defects nol, akses dua role teruji, export cocok, snapshot immutable, backup bisa dipulihkan, dependency terkunci, dokumentasi dan PROGRESS jujur. Target performa awal [TEKNIS]: daftar berpaginasi dan dashboard <2detik pada dataset uji10.000 transaksi di staging yang spesifikasinya dicatat; ukur, bukan klaim tanpa benchmark.


## Pengujian khusus Blade/Livewire

Tambahkan tests/Feature/Livewire dengan Livewire::actingAs dan Livewire::test sesuai API3.x. Uji set property, call method, assertHasErrors/assertForbidden serta keadaan database setelah action. Public method dapat dipanggil tanpa klik tombol, sehingga tes policy wajib mencakup pemanggilan langsung. Response update HTTP200 tidak berarti domain action sukses; assert jurnal/stok/alokasi dan error code.

| ID | Kasus | Hasil |
|---|---|---|
| LW01 | Pimpinan call post()/saveDraft() langsung | Ditolak, tidak ada ledger baru |
| LW02 | Ubah source ID/role/status lewat property | Ditolak atau diabaikan aman; load ulang server |
| LW03 | Akun dinonaktifkan setelah mount lalu call aksi | Ditolak oleh middleware/Action |
| LW04 | Filter periode, search dan pagination | Query benar; paginator reset; URL tersimpan |
| LW05 | Upload temporary file salah MIME/terlalu besar | Validation error; tidak menjadi bukti final |
| LW06 | Dua klik/post retry | Satu transaksi finansial efektif |
| LW07 | Hapus/urut ulang baris form barang | wire:key menjaga produk/input yang benar |
| LW08 | Render ulang grafik dan navigasi kembali | Tidak ada instance/listener ganda |
| LW09 | Logout atau session expiry saat form terbuka | Mutasi tidak lolos; tampilkan login/error jelas |
| LW10 | Blade view compile + mobile modal/fokus | Tidak ada compile error; keyboard dapat dipakai |

Jalankan php artisan view:cache, php artisan test dan npm run build. Tes JavaScript browser diperlukan untuk fokus modal, dropdown Alpine, chart lifecycle dan wire:navigate jika digunakan. Gunakan tes HTTP/controller untuk file download. Tes domain lock/idempotency tetap memakai MariaDB sungguhan. Belum ada hasil aplikasi yang dinyatakan lulus sebelum implementasi dijalankan.
