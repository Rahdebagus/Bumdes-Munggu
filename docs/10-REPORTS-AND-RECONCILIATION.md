# 10 — Enam laporan, rekonsiliasi, dan ekspor

## Kontrak ReportService

Input tervalidasi: scope unit atau konsolidasi, from/to atau period, as_of_date, basis cutover. Output: metadata filter, rows decimal strings, totals, reconciliation, generated_at, data_revision. Render HTML, PDF, XLSX memakai dataset yang sama; frontend tidak menghitung kembali angka resmi.

Ledger efektif = saldo cutover terkunci + jurnal posted_at tidak null pada rentang tanggal, termasuk original reversed dan pembalik sesuai tanggal masing-masing. Untuk saldo awal periode, ambil semua pergerakan sebelum from. Jangan memasukkan saldo akhir masa depan untuk laporan historis.

[TEKNIS] Read report dalam consistent DB snapshot; data_revision dipakai mendeteksi perubahan antara preview, submit, dan approval. Filter unit memasukkan hanya data unit tersebut; transaksi tanpa unit hanya masuk konsolidasi. Demi rekonsiliasi per-unit, transaksi baru finansial wajib unit, kecuali opening/global legacy yang diberi label belum dialokasikan. Jangan membagikan saldo global ke setiap unit.

## 1. Laba rugi

Pendapatan penjualan bersih + bunga/denda/jasa kredit terpetakan + pendapatan lain − HPP − beban. Gunakan account system/report mapping, bukan LIKE pada nama akun. HPP dari jurnal penjualan; rumus periodik PDF hanya pemeriksaan silang dengan penyesuaian stok. Jangan mengurangi beban selisih persediaan dua kali.

Baris minimal: penjualan bersih, HPP, laba kotor, pendapatan kredit, pendapatan lain, beban operasional, penyusutan, pajak jika dikonfigurasi, laba bersih. Nilai negatif diperbolehkan.

## 2. Neraca

As of date: kas/bank, piutang usaha, piutang pinjaman, persediaan, aset tetap bruto, akumulasi penyusutan negatif, aset lain, utang, modal, laba kumulatif belum ditutup. Total aset = kewajiban + ekuitas. Tidak menganggap pembayaran pokok pinjaman sebagai pendapatan atau pencairan sebagai beban.

Ekuitas = saldo akun modal/retained earnings + laba kumulatif sejak cutover yang belum ditutup. Karena versi awal tidak melakukan jurnal penutup otomatis, jangan sekaligus memasukkan laba sebagai saldo akun hasil closing.

## 3. Perubahan modal

Ekuitas awal tanggal from + penambahan modal − pengurangan/pembagian modal + laba periode = ekuitas akhir. Ekuitas awal mencakup laba periode sebelumnya yang belum ditutup. Transfer internal antarakun ekuitas tidak dihitung sebagai penambahan total modal. Ekuitas akhir sama dengan ekuitas neraca pada cutoff yang sama.

## 4. Arus kas langsung

Hanya baris kas/bank. Kelompok operasi/investasi/pendanaan disimpan pada journal line kas saat posting. Pendapatan/beban nonkas tidak muncul. Transfer kas ke bank internal harus dikecualikan dari arus kas konsolidasi (fitur transfer tersendiri belum scope; jika diimplementasi, mapping internal wajib).

Sesuai UI PDF h.109: penerimaan pokok pinjaman dan bunga masuk operasi. [ASUMSI] Pencairan pinjaman juga operasi untuk unit pemberi pinjaman. Pembelian aset investasi; modal pendanaan. Ini mapping implementasi laporan penelitian, harus ditinjau pengelola bila kebijakan pelaporan berubah.

Kas awal + arus bersih = kas akhir ledger. Satu transaksi dengan banyak kelompok harus dipecah pada baris kas yang ditandai atau alokasi eksplisit; jangan menjumlah total header berkali-kali.

## 5. Kredit

Kontrak pinjaman, peminjam, pokok awal, realisasi pokok/bunga/denda sampai cutoff, sisa pokok, jadwal jatuh tempo, tunggakan, status. Pisahkan `sisa pokok` dari `sisa tagihan` (termasuk bunga/denda). Aging dihitung terhadap as_of_date dan jadwal, bukan cache status hari ini. Pinjaman yang dicairkan setelah cutoff tidak masuk.

Piutang penjualan kredit tersedia pada subledger dagang; jangan menggantikan laporan pinjaman ini.

## 6. Omzet desa

Total = penjualan bersih + pendapatan kredit + pendapatan operasional lain terpetakan. Modal, utang, pencairan/pengembalian pokok, dan perpindahan kas bukan omzet. Denda dimasukkan pendapatan kredit hanya jika mapping disepakati; blueprint demo memasukkannya mengikuti h.59. Pajak keluaran bukan omzet.

Alur snapshot:

1. Admin membuat draft dari ReportService, filter konsolidasi satu periode.
2. Simpan nilai, akun/baris sumber, ID transaksi, bukti beserta hash/status saat snapshot, actor, waktu, policy_version, data_revision.
3. Saat submit, regenerate atau pastikan revision sama; lampiran kurang menghasilkan pesan terperinci.
4. Pimpinan approve dalam transaction yang lock periode+laporan, memeriksa revision dan bukti valid. Revision berubah → minta refresh/review; jangan approve angka lama tanpa sadar.
5. Sesudah approve, snapshot_data/totals/hash immutable. Export resmi dari snapshot, bukan live query.
6. Admin dapat melengkapi tanggal kirim/terima dan bukti penerimaan, tanpa mengubah nominal.
7. Koreksi pasca approval membuat version baru dan link previous_version_id. Versi lama tetap dapat diunduh. Satu versi resmi aktif ditetapkan melalui aturan aplikasi; jangan menghapus versi sebelumnya.

Ini pencatatan pengiriman manual. Aplikasi tidak mengirim email/WhatsApp ke desa secara otomatis karena tidak ada kebutuhan integrasi tersebut.

## Rekonsiliasi

| Check | Expected = Actual | Severity jika berbeda |
|---|---|---|
| Jurnal | Total debit = kredit per transaksi dan global | FAIL |
| Neraca | Aset = kewajiban + ekuitas | FAIL |
| Kas/bank | Ledger = dashboard = cashflow closing | FAIL |
| Stok | Nilai mutasi akhir = akun persediaan | FAIL |
| Piutang dagang | Opening+invoice−alokasi neto = ledger | FAIL |
| Utang dagang | Opening+invoice−alokasi neto = ledger | FAIL |
| Pinjaman | Sisa pokok subledger = akun piutang pinjaman | FAIL |
| Aset | Perolehan/akumulasi subledger = ledger | FAIL |
| Laba | Laba rugi = laba periode perubahan modal | FAIL |
| Modal | Perubahan modal akhir = ekuitas neraca | FAIL |
| Bukti belum valid | Semua bukti terverifikasi | WARNING; approval omzet memblokir |
| Draft tertinggal | Tidak ada draft yang perlu diposting | WARNING + review saat close |
| Penyusutan belum diposting | Semua aset eligible selesai | FAIL pada penutupan |
| Opname belum selesai | Tidak ada opname disetujui belum posted | FAIL pada penutupan |

Kesamaan uang dibandingkan decimal tepat 2 desimal, toleransi 0,00; jangan membiarkan selisih besar dengan threshold persentase. HPP rounding ditangani sebelum jurnal agar exact.

## PDF dan Excel

PDF: identitas BUMDes, judul, periode/unit, timestamp, nomor versi jika resmi, header berulang, page number, total, status draft/resmi, ruang tanda tangan jika diperlukan. Gunakan Blade khusus cetak; tidak screenshot dashboard. Tidak menampilkan tanda tangan persetujuan palsu.

XLSX: sheet Ringkasan, Rincian, Metadata; angka sebagai numeric dengan format uang, ID/nomor bukti/NIK sebagai teks. Sanitasi string berawalan `=`, `+`, `-`, `@` agar tidak menjadi formula injeksi. Nilai total mengikuti server; formula opsional tidak menjadi sumber resmi.

File ekspor disimpan private; nama `laba-rugi_2026-06_semua-unit.xlsx`. Queue untuk dataset besar. Rincian sumber dapat diikutkan untuk audit; jangan mempublikasikan NIK/telepon yang tidak diperlukan dalam laporan agregat.
