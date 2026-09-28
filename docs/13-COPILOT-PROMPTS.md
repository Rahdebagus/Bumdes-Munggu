# 13 — Prompt Copilot siap pakai

Untuk tahap00 buka folder paket ini di VS Code dan mulai sesi Copilot baru. Setelah tahap01 membuat folder aplikasi, salin docs, .github/copilot-instructions.md, dan PROGRESS.md ke root aplikasi; lanjutkan dari root aplikasi. Jalankan satu tahap setiap kali. Copilot harus menyelesaikan tahap yang diminta secara end-to-end, lalu melaporkan hasil yang dapat diverifikasi. Jangan meminta pembuatan semua modul dalam satu respons raksasa.

## Prompt pembuka / tahap 00

```text
Anda bertindak sebagai senior Laravel engineer untuk SIA BUMDes Munggu.
Baca .github/copilot-instructions.md, docs/01-SCOPE-AND-TRACEABILITY.md,
docs/02-SYSTEM-ARCHITECTURE.md, docs/15-DECISIONS-AND-SOURCES.md, dan PROGRESS.md.
Mulai proyek baru. Periksa environment lokal dan struktur folder paket ini.
Target runtime sudah ditetapkan: PHP8.2.12 dan Composer2.6.6.
Jangan menganggap folder dokumen sudah merupakan aplikasi Laravel.
Baseline sumber adalah PDF terbaru, termasuk pinjaman dan angsuran.
Gunakan Laravel12, PHP8.2.12, MariaDB10.4.32 (XAMPP, driver `mariadb`), Blade, Livewire3, Tailwind4 dan Alpine bawaan Livewire.
Gunakan config.platform.php8.2.12; semua dependency harus cocok dengan runtime ini.
Catat versi runtime/dependency aktual dan rencana tahap berikutnya di PROGRESS.md.
Identifikasi hanya konflik yang benar-benar menghalangi; pilihan rutin ikuti blueprint.
Jangan menyatakan aplikasi sudah selesai dan jangan mengarang hasil tes.
```

## Tahap 01 — scaffold, autentikasi, layout

```text
Kerjakan tahap01 menurut docs/03-SETUP-INSTALLATION.md dan docs/07-FRONTEND-BLADE-LIVEWIRE.md.
Buat skeleton Laravel12 dengan Composer yang dipin pada folder aplikasi baru,
kemudian integrasikan Blade/Livewire3/Fortify/Tailwind sesuai dokumen03.
Salin docs, .github/copilot-instructions.md dan PROGRESS.md ke root aplikasi.
Buat komponen Blade reusable: sidebar, card, table, modal, input dan badge.
Jangan memasang framework frontend terpisah atau Alpine kedua.
Sesuaikan users.nama, role admin/pimpinan, status aktif/nonaktif, session auth,
nonaktifkan registrasi publik dan buat CLI pembuatan user dengan password tersembunyi.
Buat responsive sidebar, header, breadcrumb, empty dashboard dan policy server pada setiap public mutation method Livewire.
Verifikasi login/logout, user nonaktif, larangan mutation pimpinan, build dan tes Livewire.
Catat file berubah, perintah tes yang benar-benar berjalan, dan sisa blocker.
```

## Tahap 02 — schema dan seed

```text
Baca docs/04-DATABASE-CORE.md dan docs/05-DATABASE-EXTENSIONS.md secara lengkap.
Implementasikan18 tabel inti dan4 tabel pendukung sebagai migration terpisah yang jelas.
Tambahkan seluruh FK, CHECK, unique/index, decimal cast, enum, factory dan relasi.
Atasi FK silang dengan migration setelah tabel tersedia. Jangan membuat ledger kedua.
Seed unit demo dan system_key akun yang lengkap melalui MasterDataSeeder;
DemoSeeder terpisah dari production. Uji migration/constraint pada MariaDB test (bumdes_munggu_test).
Dokumentasikan jumlah tabel bisnis vs tabel framework. Update PROGRESS.
```

## Tahap 03 — master dan opening

```text
Implementasikan MasterData dan OpeningBalance sesuai dokumen01,05,06,07,08.
Buat form/index/detail/nonaktifkan master. Larang edit stok langsung.
Implementasikan import Excel dengan preview, error per baris, batch commit atomik,
proteksi import ulang, rincian stok/piutang/utang/pinjaman/aset dan lock opening.
Saldo awal harus seimbang dan rincian sama dengan akun kontrol. Gunakan cutover awal
tahun untuk demo; jangan mengarang saldo asli. Uji invalid import dan lock mismatch.
```

## Tahap 04 — mesin posting, reverse dan audit

```text
Baca docs/06-BACKEND-MODULES.md, docs/08-ACCOUNTING-RULES.md dan docs/09-ROUTES-AND-CONTRACTS.md.
Bangun JournalPlan, JournalValidator, PostingService, ReverseTransaction,
PeriodGuard, AuditWriter, Decimal helper, request idempotency dan optimistic version.
Gunakan satu DB transaction dan lock urut. Preview dihitung ulang ketika post.
Ledger tetap menyertakan original reversed pada tanggal historisnya.
Buat tes Livewire otorisasi/state tampering serta tes domain post ganda, rollback, request hash mismatch, reverse lintas periode,
period-close race. Jangan hanya membuat method kosong/interface tanpa implementasi.
```

## Tahap 05 — dagang, kas, persediaan

```text
Bangun penjualan/pembelian tunai-kredit, pemasukan/pengeluaran dan pelunasan invoice.
Gunakan core posting tahap04, bukan jurnal manual di controller.
Implementasikan moving average, mutasi_stok, snapshot HPP, stock lock,
larangan backdate stok, reverse dependency, bukti privat dan semua UI Blade/Livewire terkait.
Alokasi piutang/utang mendukung pembayaran sebagian dan opening invoices.
Uji fixture dagang, insufficient stock serentak, overpayment, bukti dan policy.
Tampilkan drilldown dari transaksi ke jurnal dan kartu stok.
```

## Tahap 06 — usaha kredit

```text
Implementasikan kontrak pinjaman, basis_bunga wajib, total bunga, jadwal bulanan,
pencairan, pembayaran sebagian pokok/bunga/denda dan reverse alokasi.
Gunakan pembayaran_angsuran sebagai histori, cache angsuran/sisa_pokok diverifikasi.
Tanggal jangkar bulanan jangan drift; selisih pembulatan ditempatkan jadwal terakhir.
Implementasikan UI pinjaman, jadwal dan pembayaran. Uji fixture C dokumen11.
Jangan mengganti pinjaman dengan piutang penjualan atau membuat tabungan nasabah.
```

## Tahap 07 — opname, aset, penyusutan

```text
Implementasikan stocktake draft-complete-submit-approve-post dengan revisi,
policy Pimpinan, stock_version snapshot dan stale detection.
Implementasikan register aset terkait transaksi perolehan atau opening,
penyusutan garis lurus, batas residu, unique effective asset-period dan reverse/repost.
Gunakan service yang sama untuk efek ledger/stok; jangan post via queue.
Uji approval, perubahan stok selama opname dan penyusutan duplikat/rounding.
```

## Tahap 08 — laporan, dashboard, ekspor

```text
Baca docs/10-REPORTS-AND-RECONCILIATION.md dan fixture A pada dokumen11.
Bangun ReportService untuk enam laporan, buku besar dan dashboard live.
Pakai saldo cutover + jurnal efektif sesuai cutoff, bukan SUM header transaksi.
Pisahkan kas masuk vs pendapatan, piutang usaha vs pinjaman, dan laba kumulatif.
Buat export PDF/XLSX dari dataset server yang sama, private download dan filter.
Jalankan fixture A melalui Actions lalu buktikan semua expected totals cocok.
Tidak boleh ada random/hardcoded KPI, dummy chart atau TODO pada alur utama.
```

## Tahap 09 — snapshot, rekonsiliasi, periode

```text
Bangun workflow laporan desa dengan snapshot hash/revision, submit, approval,
request revision, pencatatan pengiriman/penerimaan serta revisi versi baru.
Bangun seluruh reconciliation checks dan close/reopen periode oleh Pimpinan.
Approval/close harus lock agar tidak race dengan transaksi baru.
Snapshot approved tidak berubah setelah reopen atau posting baru.
Uji live revision berubah sebelum approve, FAIL audit memblokir close,
serta role dan unduh versi resmi yang lama.
```

## Tahap 10 — QA dan UAT

```text
Jalankan matriks pada docs/11-TESTING-AND-UAT.md yang dapat diuji otomatis.
Perbaiki defect prioritas tinggi. Jalankan PHPUnit domain, Livewire::test,
php artisan view:cache, npm run build dan MariaDB integration.
Periksa UI pada360/768/1280px, keyboard, empty/loading/error states.
Buat template UAT dan pengumpulan SUS tanpa mengisi hasil responden fiktif.
Laporkan tes yang dijalankan, yang gagal, yang belum dapat dijalankan, dan alasan.
Update PROGRESS tanpa menandai manual UAT selesai sebelum ada hasil nyata.
```

## Tahap 11 — deployment dan handover

```text
Ikuti docs/14-DEPLOYMENT-AND-HANDOVER.md. Siapkan konfigurasi production,
.env.example tanpa rahasia, build assets, migration aman, worker/scheduler,
private file permissions, backup dan restore drill. Dokumentasikan runbook.
Deploy hanya pada target dan kewenangan yang diberikan pemilik proyek.
Jangan menggunakan migrate:fresh di production atau menyalin password demo.
Selesaikan smoke test, panduan Admin/Pimpinan dan daftar known issues.
```

## Prompt lanjut setelah sesi terputus

```text
Baca .github/copilot-instructions.md dan PROGRESS.md. Periksa status git dan kode
aktual; jangan mengulang tahap yang selesai. Lanjutkan task pertama yang belum
selesai pada tahap aktif. Pertahankan schema/aturan bisnis yang telah diputuskan.
Jika dokumentasi berbeda dari implementasi, jelaskan lalu perbaiki sumber konflik.
```

## Prompt perbaikan bug modular

```text
Bug: [gejala dan langkah reproduksi]. Expected: [hasil yang seharusnya].
Baca instruksi proyek serta dokumen modul terdampak. Telusuri akar masalah,
tambahkan regression test yang bermakna untuk bug finansial/otorisasi,
perbaiki dalam modul pemilik tanpa refactor luas. Jalankan tes relevan,
jelaskan perubahan dan pengaruh data historis. Update PROGRESS/decision bila perlu.
```
