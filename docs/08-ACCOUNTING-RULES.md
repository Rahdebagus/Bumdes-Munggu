# 08 — Aturan bisnis dan mesin akuntansi

Dasar: PDF h.39–60. Ini spesifikasi perangkat lunak sesuai laporan, bukan penetapan standar akuntansi atau tarif hukum baru. Kebijakan yang belum ditentukan PDF ditandai [ASUMSI] dan dapat diganti melalui keputusan tercatat.

## 1. Invariant

1. Draft tidak masuk ledger, stok, piutang, dashboard finansial, atau laporan.
2. Posted memiliki jurnal seimbang dan metadata actor/waktu. Detail finansial immutable.
3. Reversed original tetap masuk histori ledger; pembalik mengurangi efeknya pada tanggal pembalik.
4. Satu transaksi post menghasilkan seluruh efek atau tidak satu pun.
5. Nominal decimal; debit/kredit dua desimal; HPP unit enam desimal; kuantitas empat desimal. Pembulatan HALF_UP pada nilai baris, total jumlah nilai baris.
6. Stok tidak negatif; pembayaran tidak melebihi outstanding; penyusutan tidak menurunkan nilai buku di bawah residu.
7. Transaksi berada pada periode tanggalnya, tidak boleh post pada periode ditutup.
8. Saldo akun kontrol harus cocok dengan subledger. Jangan mengizinkan jurnal kas generik menyentuh kontrol tanpa rincian.

## 2. Matriks jurnal

| Peristiwa | Debit | Kredit | Efek domain |
|---|---|---|---|
| Penjualan tunai | Kas/bank sebesar tagihan | Penjualan bersih, pajak keluaran bila berlaku | Stok berkurang |
| Penjualan kredit | Piutang usaha | Penjualan bersih, pajak keluaran bila berlaku | Outstanding invoice |
| HPP penjualan | HPP | Persediaan | Nilai sesuai snapshot mutasi |
| Pembelian tunai | Persediaan; pajak masukan bila berlaku | Kas/bank | Stok bertambah |
| Pembelian kredit | Persediaan; pajak masukan bila berlaku | Utang usaha | Outstanding invoice |
| Pemasukan jasa | Kas/bank | Pendapatan jasa | Tanpa stok |
| Penambahan modal | Kas/bank | Modal | Bukan omzet |
| Pelunasan piutang dagang | Kas/bank | Piutang usaha | Alokasi ke invoice |
| Pengeluaran beban | Beban yang dipilih | Kas/bank | Beban periode |
| Pembayaran utang dagang | Utang usaha | Kas/bank | Alokasi ke invoice |
| Pengurangan modal | Modal | Kas/bank | Bukan beban |
| Pencairan pinjaman | Piutang pinjaman | Kas/bank | Kontrak aktif + jadwal |
| Angsuran | Kas/bank total | Piutang pinjaman pokok; pendapatan bunga; pendapatan denda | Alokasi jadwal |
| Perolehan aset tunai | Aset tetap | Kas/bank | Register aset terkait |
| Perolehan aset kredit | Aset tetap | Utang usaha | Register aset + invoice kewajiban |
| Penyusutan | Beban penyusutan | Akumulasi penyusutan | Satu efektif/aset/periode |
| Opname kurang | Beban selisih persediaan | Persediaan | Stok turun |
| Opname lebih | Persediaan | Pendapatan/penyesuaian persediaan terpetakan | Stok naik |
| Reverse | Debit/kredit sumber ditukar | Lawan sumber | Subledger dibalik |

Perolehan aset kredit menggunakan transaksi jenis pengeluaran dengan `metode_pembayaran=kredit`, akun_kas_id NULL, lawan utang, sumber aset_tetap; bukan memaksakan kas keluar fiktif. Alokasi pelunasan harus menerima invoice sumber pengeluaran-aset kredit ini selain pembelian barang kredit. Sesuaikan validator dokumen 05 dengan subtype ini. Tidak ada jenis transaksi operasional tambahan.

## 3. Diskon dan pajak

[ASUMSI] Input harga sebelum pajak, diskon nominal per baris. `net = round(qty*harga,2)-diskon`; `subtotal=net+pajak`. Header total = jumlah subtotal. Diskon antara 0 dan nilai bruto. Pajak default 0, hanya dapat diisi jika mapping akun dan kebijakan terkonfirmasi. Tidak menanam tarif PPN tertentu atau menyebut patuh pajak tanpa validasi pengelola.

Pembelian: nilai persediaan = net + pajak yang memang tidak dapat dikreditkan sesuai kebijakan. Untuk versi awal mode default pajak=0; mode pajak masukan terpisah harus eksplisit di configuration dan diuji. Harga jual master tidak menentukan HPP.

## 4. Persediaan dan HPP

[ASUMSI] **Perpetual moving weighted average** karena PDF mencatat HPP pada setiap penjualan tetapi belum memilih FIFO/rata-rata. PDF h.57 juga memuat rumus periodik; gunakan sebagai rekonsiliasi dengan penyesuaian, jangan mem-posting HPP kedua kali.

- Saldo awal: qty dan nilai dari rincian opening terkunci.
- Pembelian: nilai_baru = nilai_lama + biaya_perolehan; qty_baru = qty_lama + qty_beli; average = nilai_baru/qty_baru.
- Penjualan: HPP = round(qty_jual × average_sebelum,2); jika menghabiskan stok, ambil seluruh saldo nilai tersisa agar tidak meninggalkan nilai tanpa kuantitas.
- Store harga_pokok dan nilai_pokok historis; jangan hitung ulang penjualan lama memakai harga master sekarang.
- Opname kurang/lebih menggunakan average saat snapshot; jika stok nol dan perlu penambahan, harga perolehan harus diinput dengan bukti dan disetujui, bukan dibagi nol.
- Audit: opening inventory + purchases + adjustment gains − HPP − adjustment losses = ending inventory.

Contoh: 10 unit × 10.000 =100.000; beli 10 ×14.000 =140.000; saldo20 nilai240.000 average12.000; jual5 → HPP60.000; saldo15 nilai180.000.

[TEKNIS] Posting stok mundur sebelum mutasi terakhir produk ditolak. Dalam tanggal sama urutan posting menentukan HPP. Koreksi mutasi lama yang sudah diikuti transaksi lain membutuhkan pembalikan dependensi berurutan; tidak mengubah cost histori secara diam-diam.

Opname menyimpan stock_version_snapshot. Saat post, jika ada mutasi baru sejak hitung, tolak stale snapshot dan minta hitung/review ulang serta approval ulang. Tidak menutup seluruh toko berhari-hari hanya karena draft opname.

## 5. Pinjaman dan jadwal

PDF: angsuran pokok = pokok/tenor; bunga = total bunga/tenor. Basis persentase tidak dijelaskan.

[ASUMSI] Tersedia dua basis eksplisit:

- flat_total: total_bunga = pokok × rate/100 untuk seluruh tenor.
- flat_bulanan: total_bunga = pokok × rate/100 × tenor.

UI wajib memilih basis dan menampilkan total bunga serta total tagihan sebelum cair. Tidak menghitung bunga majemuk, efektif, penalti otomatis, atau restrukturisasi tanpa scope tambahan. Denda manual beralasan, dimasukkan ke jadwal melalui action terotorisasi dan audit, default 0.

Bagikan pokok/bunga per bulan ke dua desimal; selisih pembulatan ditempatkan pada jadwal terakhir. Jumlah semua pokok_tagihan persis pokok kontrak. Tanggal pertama eksplisit, bulan berikut mengikuti tanggal jangkar; tanggal31 pada bulan pendek memakai hari terakhir bulan tanpa drift jangkar ke28.

Pembayaran boleh sebagian dan beberapa jadwal, dengan alokasi pokok/bunga/denda eksplisit. Tolak komponen lebih bayar. Setelah pembayaran, recompute cache dari alokasi efektif. Lunas bila semua pokok+bunga+denda selesai; sisa pokok nol belum selalu berarti seluruh kontrak lunas.

Pendapatan bunga/denda diakui ketika pembayaran posted mengikuti pola PDF, bukan accrued otomatis. Status keterlambatan dihitung terhadap `as_of_date` laporan, bukan selalu tanggal hari ini. Pinjaman macet tidak otomatis dihapuskan.

## 6. Pelunasan dagang

Penjualan kredit mengakui pendapatan sekali saat post. Pelunasannya hanya mengurangi piutang. Pembelian kredit mencatat utang sekali; pembayaran hanya mengurangi utang. Alokasi wajib menunjuk invoice atau opening item, cocok pihak/unit, maksimal outstanding. Tidak mencatat pelunasan ulang pada menu penjualan.

Versi awal pilihan tunai atau kredit penuh. Pembayaran sebagian setelah invoice kredit melalui pemasukan/pengeluaran. DP pada formulir penjualan bukan scope baseline; jika diminta, perlakukan sebagai workflow baru dengan aturan jurnal jelas.

## 7. Aset dan penyusutan

Harga perolehan >= residu >=0, umur>0. [ASUMSI] Penyusutan bulanan penuh mulai `tanggal_mulai_susut` yang dikonfirmasi saat input; tidak prorata harian. Nilai = (perolehan-residu)/umur bulan; terakhir disesuaikan ke sisa basis. Aset migrasi membawa akumulasi_awal; remaining depreciable amount tidak boleh negatif.

Perolehan aset terkait transaksi tepat sekali; membuat master aset tidak otomatis menggandakan jurnal. Aset existing opening tidak membuat kas keluar baru. Key penyusutan unik per aset+periode, dengan mekanisme reverse/repost pada dokumen 05.

## 8. Saldo awal dan periode

Tetapkan satu tanggal cutover per implementasi. Saldo awal akun diisi pada periode cutover, dikunci setelah seimbang dan semua rincian cocok. Periode berikutnya menghitung saldo bawa dari ledger; **jangan menjumlah saldo awal bulanan berulang**. Tabel boleh menampung carry-forward snapshot untuk optimasi nanti tetapi harus ditandai dan tidak dijumlah sebagai transaksi baru; versi pertama cukup saldo cutover.

Saldo awal inventori/pinjaman/piutang/aset wajib mempunyai rincian. Migrasi pinjaman berjalan memuat sisa pokok, jadwal tersisa, dan referensi lama; tidak mem-posting pencairan historis lagi. Total rincian saldo kontrol harus sama sebelum lock. Opening P&L untuk cutover tengah tahun diberi YTD origin dan tidak dianggap pendapatan bulan cutover; metadata dan fixture terpisah wajib untuk mode tersebut. Mode default demo cutover awal tahun agar tidak ambigu.

Tutup bulanan mengunci periode, tidak membuat jurnal penutup P&L otomatis. Neraca menampilkan laba kumulatif berjalan sebagai komponen ekuitas yang dihitung dari ledger; jangan menambahnya lagi bila kelak jurnal penutup resmi diperkenalkan. Reopen mengharuskan validasi ulang periode sesudahnya; snapshot resmi lama tetap tetap.

## 9. Reverse lintas periode

Contoh beban100.000 tanggal30 Juni, dibalik2 Juli. Juni tetap beban100.000; Juli pembalik mengurangi beban100.000; kumulatif sampai Juli neto0. Mengeluarkan original karena status reversed akan membuat Juni salah. Tes ini wajib sebelum laporan dianggap selesai.

Reverse snapshot laporan desa bukan edit nominal snapshot. Buat versi laporan baru dengan referensi versi sebelumnya jika koreksi diperlukan; simpan alasan dan approval baru.
