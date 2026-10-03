# QA Traceability & UAT — SIA BUMDes Munggu (tahap10)

Dokumen ini memetakan matriks pengujian dokumen11 (T01–T26) ke tes otomatis yang
benar-benar dijalankan, dan mencatat status UAT. **Tidak ada hasil yang dikarang.**
Butir yang membutuhkan sesi manusia (UAT fungsional oleh pengguna, SUS, uji
responsif/keyboard, restore drill) ditandai **MENUNGGU** — bukan PASS.

- Lingkungan uji: MariaDB 10.4.32 (XAMPP), database `bumdes_munggu_test`, PHP 8.2.12, Laravel 12.69.2.
- Perintah: `php artisan test`.
- Hasil terakhir (3 Okt 2026): **145 passed / 477 assertions**, 0 gagal.
- Build: commit tahap10 pada branch `main` (lihat `git log`). Database production tidak diuji.

Legenda status: **PASS** = terbukti tes otomatis di MariaDB; **PARTIAL** = jalur
utama terbukti, aspek konkurensi/real-race belum diuji deterministik (lock ada);
**DESAIN** = dijamin mekanisme framework (binding/allowlist), bukan uji khusus;
**MENUNGGU** = perlu sesi manusia/operasional (tahap10 UAT atau tahap11).

## Matriks T01–T26

| ID | Skenario | Status | Bukti (tes) / catatan |
|---|---|---|---|
| T01 | Login salah/nonaktif, logout | PASS | `Auth/LoginTest`, `Auth/EnsureUserActiveTest` |
| T02 | Pimpinan saveDraft/post via Livewire | PASS | `Livewire/TradingUiTest`, `StocktakeUiTest`, `PeriodUiTest`, `LaporanDesaUiTest` (403, tanpa row) |
| T03 | Draft tersimpan, saldo tetap | PASS | `Qa/QaMatrixTest::t03` (0 transaksi_detail, posted_at null) |
| T04 | Post dua kali/refresh retry | PASS | `Accounting/PostingServiceTest::double_post_same_key_is_idempotent` |
| T05 | Request key sama payload beda | PASS | `PostingServiceTest::same_key_different_hash_conflicts` |
| T06 | Ubah property/posted transaksi | PASS | `PostingServiceTest::optimistic_version_mismatch`; `MasterDataTest::used_account_cannot_change_meaning` |
| T07 | Exception setelah insert jurnal | PASS | `PostingServiceTest::unbalanced_is_rejected_and_rolled_back` (0 efek) |
| T08 | Akun nonaktif/salah jenis | PASS | `Qa/QaMatrixTest::t08` (INVALID_STATE, 0 detail) |
| T09 | Closed period vs posting | PARTIAL | `PostingServiceTest::post_rejected_on_closed_period`, `PeriodCloseTest`; lock `lockForUpdate` ada, race serentak belum diuji deterministik |
| T10 | Reverse lintas periode | PASS | `Period/PeriodCloseTest::fixture_b_reverse_lintas_periode_dan_juni_immutable` |
| T11 | Pembayaran parsial/reverse | PASS | `Lending/LendingTest` (Fixture C: split 333.333,33/.33/.34, reverse pulih, overpayment ditolak) |
| T12 | Stok negatif/konkurensi/opname stale | PARTIAL | `Trading/TradingPostingTest` (insufficient+rollback, backdate), `ReverseTradingTest` (REVERSE_DEPENDENCY), `Inventory/StocktakeTest` (stale); race dua request serentak belum diuji deterministik (guard+lock ada) |
| T13 | Depreciation duplicate/cap | PASS | `FixedAssets/DepreciationTest` + `Unit/DepreciationCalculatorTest` (Fixture E: 900.000, cap residu, DEPRECIATION_EXISTS) |
| T14 | Opening tidak seimbang | PASS | `OpeningBalance/LockTest::lock_rejected_when_unbalanced` |
| T15 | Opening rincian ≠ kontrol | PASS | `LockTest::lock_rejected_when_rincian_mismatch_control_account` |
| T16 | Import salah kolom/duplikat/lokal | PASS | `OpeningBalance/ImportTest` (error per baris, tanpa partial commit) |
| T17 | Filter periode/unit | PASS | `Qa/QaMatrixTest::t17` (dagang 100.000 + kredit 12.000 = konsolidasi 112.000; piutang pinjaman hanya unit kredit) |
| T18 | Snapshot approved lalu data berubah | PASS | `Reporting/LaporanDesaTest` (REVISION_CHANGED; versi baru previous_version_id; versi lama immutable) |
| T19 | Approval sendiri/tanpa hak | PASS | `Livewire/EvidenceTest::uploader_cannot_verify_own`, `LaporanDesaUiTest::admin_tidak_boleh_approve`, `StocktakeUiTest::admin_cannot_approve` |
| T20 | Upload executable/MIME/>5MiB | PASS | `Qa/QaMatrixTest::t20` (mimes pdf/jpg/png, max 5120 KB → error) |
| T21 | Unduh bukti tanpa login/traversal | PASS | `Qa/QaMatrixTest::t21` (redirect login; path via route-model-binding, tidak dari klien) |
| T22 | Export XLSX string formula | PASS | `Reporting/ReportExportTest::xlsx_sanitasi_formula_injection` (`=…` → `'=…`) |
| T23 | Edit master stok langsung | PASS | `Livewire/MasterDataTest::save_product_ignores_direct_stock_fields` |
| T24 | Pagination/search SQL injection | DESAIN | Query Eloquent ber-binding; pencarian `#[Url]` tanpa raw SQL; tak ada input kolom dinamis. Belum ada uji injeksi khusus |
| T25 | Viewport 360/768/1280 + keyboard | MENUNGGU | Perlu uji browser/manual (belum dijalankan) |
| T26 | Restore backup terpisah | MENUNGGU | Restore drill = tahap11 (deploy/handover) |

## Ringkasan tes otomatis

Suite dijalankan penuh pada MariaDB `bumdes_munggu_test`:

- Unit: kalkulator decimal, jadwal angsuran, penyusutan.
- Feature: Actions + HTTP + policy + fixture keuangan A–E, laporan/ekspor, snapshot, periode.
- Livewire: alur role utama (Admin mutasi, Pimpinan approval/baca).
- Total **145 passed / 477 assertions**, 0 gagal.

Fixture keuangan dokumen11 yang cocok exact: **A** (lintas modul, laba 112.000 / omzet
762.000 / kas 7.062.000 / arus operasi −1.738.000), **B** (reverse lintas periode),
**C** (angsuran parsial), **D** (stok/HPP + guard reverse/stale), **E** (penyusutan 900.000).

## UAT (menunggu pelaksanaan nyata)

Status: **BELUM DIJALANKAN.** UAT fungsional memerlukan dua penguji nyata (Admin &
Pimpinan) menjalankan alur dokumen11 §UAT. Jangan mengisi PASS sebelum sesi nyata.

Alur yang harus diuji:
- **Admin:** master → opening → beli → jual → kredit → bukti → post → reverse → aset → opname → ekspor.
- **Pimpinan:** dashboard → audit → approval opname → approval omzet → close/reopen.

Template catatan (isi saat sesi nyata, satu baris per skenario):

| ID | Langkah | Expected | Actual | Pass/Fail | Catatan | Severity | Bukti | Tester | Tanggal | Build SHA |
|---|---|---|---|---|---|---|---|---|---|---|
| | | | | | | | | | | |

## SUS (menunggu responden)

**BELUM DIISI.** Gunakan 10 butir SUS instrumen penelitian (skala 1–5): skor item
ganjil = response−1; genap = 5−response; total × 2,5 → 0–100. Skor SUS **bukan**
persentase keberhasilan dan tidak menggantikan bukti perhitungan benar. Jangan
mengklaim skor sebelum responden mengisi.

## Definition of Done global — status

- [x] Semua fitur berfungsi pada data MariaDB (suite hijau di `bumdes_munggu_test`)
- [x] Fixture keuangan cocok (A–E exact)
- [x] Akses dua role teruji (Admin mutasi / Pimpinan approval)
- [x] Export cocok (PDF/XLSX dari dataset sama; sanitasi formula)
- [x] Snapshot immutable + versi
- [x] Dependency terkunci (composer.lock; `check-platform-reqs` lulus)
- [x] Dokumentasi & PROGRESS jujur
- [ ] Critical/high defects nol — **belum final**: menunggu UAT manusia + T25/T26
- [ ] Backup bisa dipulihkan (restore drill) — tahap11
- [ ] Benchmark performa <2 detik pada dataset uji — [TEKNIS] belum diukur (butuh dataset & staging)
