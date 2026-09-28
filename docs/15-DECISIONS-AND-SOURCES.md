# 15 — Decision register dan sumber

## Status keputusan

`BASELINE`: ditulis dalam PDF. `TEKNIS`: rancangan implementasi dalam paket ini. `VALIDASI OPERASIONAL`: dapat digunakan demo tetapi perlu disahkan pemilik proses sebelum data nyata. Register ini tidak berarti pengguna telah menyetujui seluruh asumsi.

| ID | Keputusan | Status / alasan |
|---|---|---|
| ADR-01 | Laravel12 + PHP8.2.12 + MariaDB10.4.32; Blade Livewire3 Tailwind4 Alpine | Konfigurasi proyek berdasarkan runtime pengguna; engine diperbarui oleh ADR-23 |
| ADR-02 | Dua role Admin/Pimpinan | BASELINE BAB III mengatasi batasan BAB I yang belum selaras |
| ADR-03 | Usaha pinjaman dan angsuran termasuk scope | BASELINE PDF terbaru h.39–59,82–84,110 |
| ADR-04 |18 core+4 supporting tables | TEKNIS; menjaga riwayat parsial dan rekonsiliasi; perlu revisi PDM |
| ADR-05 | HPP perpetual moving average | VALIDASI OPERASIONAL; PDF tidak menyebut metode eksplisit |
| ADR-06 | Bunga flat_total/flat_bulanan wajib dipilih | VALIDASI OPERASIONAL; tidak ada default tarif nyata |
| ADR-07 | Denda manual beralasan, default0 | VALIDASI OPERASIONAL; tidak mengarang tarif penalti |
| ADR-08 | Bunga diakui saat pembayaran | BASELINE pola jurnal PDF; bukan accrual otomatis |
| ADR-09 | Penyusutan bulanan penuh mulai tanggal yang ditetapkan | VALIDASI OPERASIONAL; PDF hanya rumus garis lurus |
| ADR-10 | Saldo cutover sekali; monthly opening diturunkan | TEKNIS; mencegah double count |
| ADR-11 | Laba kumulatif di ekuitas, tanpa jurnal penutup otomatis | TEKNIS; cegah penggandaan ekuitas |
| ADR-12 | Reversed original tetap dihitung bersama pembalik | TEKNIS koreksi ambiguitas filter posted PDF |
| ADR-13 | Backdate stok ditolak, reverse dependensi berurutan | TEKNIS; algoritma revaluasi historis bukan scope awal |
| ADR-14 | Bukti belum diperiksa warning; bukti ditolak block | VALIDASI OPERASIONAL; approval omzet perlu bukti valid |
| ADR-15 | Upload5MiB PDF/JPEG/PNG dan private disk | TEKNIS |
| ADR-16 | Pajak default0, mapping eksplisit bila dipakai | VALIDASI OPERASIONAL; tidak menetapkan tarif hukum |
| ADR-17 | Pokok pinjaman cashflow operasi | BASELINE h.109; pencairan operasi asumsi konsisten unit kredit |
| ADR-18 | No public signup; user via CLI | TEKNIS menjaga dua role |
| ADR-19 | UI terang aksen emerald | TEKNIS; mudah diganti token warna |
| ADR-20 | Snapshot resmi konsolidasi per periode dan versi | TEKNIS turunan kebutuhan snapshot PDF |

## Ketidakkonsistenan sumber yang perlu dibereskan pada naskah

- Kata pengantar h.ii masih menyebut judul sistem informasi geografis, berbeda dari judul penelitian SIA. Ini masalah naskah, bukan scope aplikasi.
- Daftar tabel berisi `Error! Bookmark not defined.` dan nomor/subbab tidak semuanya selaras.
- Batasan masalah h.3 hanya menyebut Admin, sedangkan BAB III menyebut Admin/Pimpinan dengan approval.
- PDM gambar h.76 memuat variasi nama field dari kamus tertulis h.77–88; blueprint mengikuti kamus tabel tertulis.
- Narasi `kategori_transaksi` tidak disertai tabel pada18 tabel inti; implementasi kategori config diberi label teknis.
- Rumus HPP periodik h.57 harus diselaraskan dengan posting HPP perpetual h.53–54 agar implementasi tidak menghitung ganda.
- Pembayaran sebagian butuh banyak alokasi, sementara angsuran.transaksi_id tunggal tidak menyimpan semua histori.
- Kalimat membaca transaksi posted perlu menjelaskan bahwa original reversed tetap dipertahankan dalam ledger bersama pembalik.

Paket ini tidak mengubah PDF sumber. Gunakan register ini bila melakukan revisi laporan berikutnya.

## Sumber utama

**SEMINAR REVISI SENJA BAB 123.pdf**, lampiran pengguna,142 halaman. Judul: Rancang Bangun Sistem Informasi Akuntansi Berbasis Web untuk Pengelolaan Pelaporan Keuangan pada BUMDes Munggu. Penulis Ni Komang Ayu Senja Pratiwi. Nomor halaman yang dirujuk adalah halaman tercetak.

Prioritas pembacaan: kebutuhan h.39–52; arsitektur dan aturan h.53–60; DFD/CDM/PDM dan tabel h.61–90; UI h.91–112; pengujian h.112–124. Ekstraksi teks dan inspeksi visual halaman PDM digunakan untuk membaca struktur.

## Dokumentasi teknis resmi

Diperiksa28 September2026; gunakan lockfile aktual untuk minor version. Tautan tidak menyatakan semua perintah telah dijalankan pada komputer pengguna.

- [shadcn/ui](https://ui.shadcn.com/): referensi visual saja, bukan dependency implementasi.
- [Blade Laravel12](https://laravel.com/docs/12.x/blade): template dan komponen server-side.
- [Livewire3 installation](https://livewire.laravel.com/docs/3.x/installation): integrasi PHP dan Alpine bawaan.
- [Laravel12 installation](https://laravel.com/docs/12.x/installation): runtime dan project scaffold.
- [Fortify Laravel12](https://laravel.com/docs/12.x/fortify): backend autentikasi dengan view Blade proyek.
- [Laravel12 deployment](https://laravel.com/docs/12.x/deployment): PHP requirement, public document root dan konfigurasi production.

Panduan spesifik package export/decimal harus diperiksa Copilot pada saat dependency dipasang; versinya belum di-lock karena deliverable ini blueprint, bukan repository aplikasi yang sudah dibangun.

## Keputusan sebelum go-live

Pengelola meninjau chart of accounts, mapping laporan, opening+subledger, dasar bunga, denda, awal penyusutan, HPP, pajak jika dipakai, verifikasi bukti dan persetujuan laporan. Review ini tidak menghalangi pembangunan dan pengujian demo sintetis.

## ADR-21 — runtime mengikuti komputer pengguna

PHP8.2.12 dan Composer2.6.6 merupakan runtime awal proyek. Laravel12 mendukung PHP8.2 menurut [release notes resmi](https://laravel.com/docs/12.x/releases). Gunakan PHPUnit11 dan config.platform.php8.2.12. Periksa platform requirements nyata setelah install. Node, service MariaDB dan path executable diperiksa lokal. Paket ini berisi spesifikasi; implementasi dimulai pada tahap00.

## ADR-22 — frontend full Laravel

Gunakan Blade+Livewire3 sebagai frontend Laravel, Tailwind4 sebagai CSS, Alpine bawaan Livewire untuk interaksi lokal. Tidak membutuhkan frontend framework terpisah. Komponen Blade dibuat modular. Livewire3 dipin agar setup, lifecycle dan pengujian konsisten dengan dokumentasi versi yang digunakan. Setiap action Livewire wajib validasi dan policy, lalu memanggil domain yang sama seperti CLI/import.

## ADR-23 — engine database MariaDB 10.4.32 (menggantikan target MySQL 8.4)

Status: **TEKNIS / disetujui pemilik proyek 28 September 2026.** Menggantikan target MySQL 8.4 pada ADR-01 dan seluruh dokumen. Alasan: engine yang benar-benar terpasang dan berjalan di komputer pengguna adalah MariaDB 10.4.32 (bundel XAMPP, 127.0.0.1:3306); pemilik memilih memakainya, bukan memasang MySQL terpisah, dan meminta jangan mengganti server database. Runtime diverifikasi 28 Sep 2026: `10.4.32-MariaDB`, `sql_mode` global longgar (`NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION`), `innodb_default_row_format=dynamic`, collation server `utf8mb4_general_ci`; database `bumdes_munggu` sudah ada dan kosong, `bumdes_munggu_test` belum ada.

Implikasi (detail dokumen03 §MariaDB 10.4):
- Koneksi memakai driver **`mariadb`** Laravel 12 (`DB_CONNECTION=mariadb`), bukan `mysql`, agar collation default `utf8mb4_unicode_ci` (bukan `utf8mb4_0900_ai_ci` khusus MySQL 8 yang tidak ada di MariaDB) dan grammar MariaDB dipakai.
- `'strict' => true` dipertahankan agar sql_mode sesi ketat meski global XAMPP longgar (integritas nilai keuangan).
- CHECK ditegakkan (MariaDB ≥10.2.1); generated column unique key harus STORED; kolom `json` = LONGTEXT + `json_valid` dan dibaca/tulis sebagai dokumen utuh via Eloquent cast.
- Invariant "tes MySQL nyata" menjadi "tes MariaDB nyata" pada `bumdes_munggu_test`.
- Batasan pemilik: jangan upgrade PHP, jangan ganti server DB, jangan hapus DB/pekerjaan yang ada, jangan `migrate:fresh`. Bila koneksi/migration gagal, laporkan error nyata.
- Risiko sisa: bila production kelak memakai MySQL 8, perbedaan JSON biner, collation, dan beberapa fungsi perlu diuji ulang; samakan versi MariaDB antar-lingkungan (dokumen14).
