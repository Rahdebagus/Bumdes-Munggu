# Progress implementasi

Status: **TAHAP00–05 SELESAI** (per 1 Okt 2026). Tahap05 lengkap & teruji di MariaDB: **dagang + persediaan/HPP** (moving-average, `mutasi_stok`, larangan backdate & stok negatif), **kas + pelunasan** (tanpa pendapatan ganda, tolak lebih bayar), **reverse** untuk jurnal/dagang/kas (membalik stok + pelunasan, dengan dependency guard), **bukti transaksi privat** (upload/verify/reject/unduh berpolicy), dan **UI Blade/Livewire** (penjualan/pembelian/kas, daftar + detail transaksi dengan drilldown jurnal & kartu stok + posting/reverse + bukti, persediaan + kartu stok). Berikutnya tahap06 (usaha kredit: pinjaman/jadwal/angsuran). PROGRESS dikelola dari root aplikasi `bumdes-munggu/`.

## Environment aktual

Diverifikasi lokal 28 September 2026 (Windows 11, PowerShell) lewat `php -v`, `composer --version`, `php -m`, `mysql --version`, dst — bukan asumsi, bukan screenshot.

- PHP: **8.2.12** CLI (ZTS, VC++2019 x64, build 24 Okt 2023) di `C:\xampp\php\php.exe`, ada di PATH — sesuai target ✔
- Composer: **2.6.6** (2023-12-08) di `C:\ProgramData\ComposerSetup\bin\composer.bat` — sesuai target ✔
- php.ini aktif: `C:\xampp\php\php.ini`
- Ekstensi wajib dokumen03 (pdo_mysql, mbstring, openssl, fileinfo, dom, xml, curl, zip): **semua ada** ✔; bcmath ✔, gd ✔; `intl` **tidak ada** (bukan wajib dokumen03; aktifkan bila butuh format lokal/uang)
- Node: **v22.22.2**; npm: **8.15.0** (lebih tua dari bawaan Node 22, tetapi `npm install` + `npm run build` / Vite **7.3.6** **berhasil** di tahap01 — dipantau, belum menghalangi); git: 2.37.1
- Database: **MariaDB 10.4.32** (XAMPP) di 127.0.0.1:3306 — **engine resmi proyek** (ADR-23). Koneksi Laravel via driver `mariadb`, `'strict' => true` (sql_mode global XAMPP longgar), charset/collation `utf8mb4`/`utf8mb4_unicode_ci`. `php artisan db:show` mengonfirmasi koneksi.
- Aplikasi ter-scaffold di `bumdes-munggu/` (terpisah dari paket dokumen `bumdes-munggu-copilot/`). Versi terkunci (composer.lock): Laravel framework **v12.69.2**, Livewire **v3.8.9**, Fortify **v1.40.0**, PHPUnit **11.5.56** (dev), Pint **v1.30.4** (dev), brick/math 0.14.8 (transitif). `config.platform.php=8.2.12`; `composer check-platform-reqs` **lulus**.
- Frontend: Tailwind **4** + Vite **7.3.6** (bawaan skeleton Laravel 12); Alpine bawaan Livewire (tidak ada Alpine kedua / framework FE lain). `resources/js/app.js` hanya helper, tanpa `Alpine.start()`.
- Repository / branch: **git diinisialisasi** di `bumdes-munggu/` (branch `main`), baseline tahap00–02 = commit `ed53039` (belum ada remote). +phpoffice/phpspreadsheet ^5.10 (tahap03, impor Excel).
- Database `bumdes_munggu`: collation kini `utf8mb4_unicode_ci`; berisi **10 tabel framework** (users, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, password_reset_tokens, passkeys, migrations) setelah `php artisan migrate`. `bumdes_munggu_test` dibuat untuk tes; user aplikasi `bumdes_app`@localhost. **Belum ada 18+4 tabel bisnis** (tahap02).

## Tahapan

- [x]00 Scope/ADR dan environment
- [x]01 Scaffold, auth, policy, shell Blade/Livewire
- [x]02 Migration18 inti+4 pendukung, model, seed
- [x]03 Master, opening/import/rincian/lock
- [x]04 Posting, reverse, audit, period guard
- [x]05 Dagang, kas, stok, pelunasan, bukti
- [ ]06 Pinjaman, jadwal, angsuran parsial
- [ ]07 Opname, approval, aset, penyusutan
- [ ]08 Enam laporan, dashboard live, PDF/XLSX
- [ ]09 Snapshot desa, rekonsiliasi, close/reopen
- [ ]10 QA, UAT dan perbaikan
- [ ]11 Deploy, restore drill, handover

## Task aktif

**Tahap00 SELESAI** (28 Sep 2026): dokumen 01/02/03/15 + `.github/copilot-instructions.md` dibaca; environment diverifikasi di komputer pengguna; struktur paket dikonfirmasi docs-only (belum ada aplikasi Laravel).

**BLOCKER TERSELESAIKAN (28 Sep 2026):** pemilik memilih opsi (b) — engine resmi menjadi **MariaDB 10.4.32** (XAMPP). Dicatat sebagai **ADR-23** dan diselaraskan ke seluruh dokumen (copilot-instructions, dokumen02/03/04/11/12/13/14/15, README). Ringkasan penyesuaian: koneksi driver `mariadb` (bukan `mysql`) agar collation default `utf8mb4_unicode_ci`; `'strict' => true`; CHECK ditegakkan MariaDB ≥10.2.1; generated column unique key STORED; kolom `json` = LONGTEXT+`json_valid`; "tes MySQL nyata" → "tes MariaDB nyata". Batasan pemilik dihormati: tanpa upgrade PHP, tanpa ganti server DB, tanpa hapus DB/pekerjaan, tanpa `migrate:fresh`.

**TAHAP01 SELESAI (28 Sep 2026).** Yang dibangun & terverifikasi:
- Scaffold Laravel 12 terpin + `composer install` + `check-platform-reqs` lulus; `.env` MariaDB (driver `mariadb`), APP_KEY digenerate, `.env.example` tanpa rahasia.
- Auth Fortify login-only: `Fortify::loginView` → `auth.login`; **registrasi publik nonaktif** (route `/register` 404); `Fortify::authenticateUsing` menolak user nonaktif; rate limit login bawaan; logout POST+CSRF.
- `users.nama` (bukan `name`), `role` (admin/pimpinan) & `status` (aktif/nonaktif) sebagai VARCHAR + PHP enum (`App\Enums\UserRole`, `UserStatus`); factory + state `pimpinan()`/`nonaktif()`.
- Middleware `EnsureUserActive` sebagai alias route `active` **dan** persistent middleware Livewire (`AppServiceProvider`); Gate `perform-admin-mutation` (Admin saja).
- CLI `php artisan app:create-user` dengan password tersembunyi (secret) + konfirmasi; membuat akun `admin@bumdes-munggu.test` (admin) & `pimpinan@bumdes-munggu.test` (pimpinan) — kredensial dev lokal.
- Shell UI: layout `app`/`guest`, sidebar responsif (menu dokumen07; hanya Dashboard aktif, lainnya ditandai "segera"), header + user menu + breadcrumb, empty dashboard jujur (KPI "—", tanpa angka acak). Komponen reusable `x-ui.button/input/card/badge/table/modal/breadcrumb`. Tailwind4/Vite build OK. Tidak ada framework FE terpisah / Alpine kedua.
- Contoh policy server pada public mutation Livewire (`Dashboard::clearReportCache` → `authorize('perform-admin-mutation')`) sebagai pola untuk tahap berikutnya.

**TAHAP02 SELESAI (28 Sep 2026).** Yang dibangun & terverifikasi:
- **22 tabel bisnis** = 18 inti + 4 pendukung, tiap tabel migration terpisah (`2026_09_28_1000xx`), plus 1 migration FK-silang `opening_rincian_id` (dokumen05 langkah 3). Tidak ada ledger kedua (jurnal dari `transaksi_detail`).
- FK RESTRICT, **14 CHECK** bernama (debit/kredit XOR, XOR target alokasi, XOR sumber+arah mutasi, residu≤perolehan, bulan 1–12, dll.), UQ/index sesuai dokumen04 §Indeks, decimal cast (N=19,2 / Q=18,4 / C=19,6), **generated STORED** `scope_unit_id` & `target_key`, kolom `json` (LONGTEXT+`json_valid` di MariaDB).
- 21 model Eloquent (+User) dengan `$guarded`, cast enum/decimal/array/date, relasi; 17 enum backed (`App\Enums\*`) untuk status/jenis; `UPDATED_AT=null` pada tabel append-only (log, pembayaran_angsuran, alokasi_pelunasan, mutasi_stok, saldo_awal_rincian).
- `config/accounting.php`: 20 `system_key`, mapping kategori→akun lawan berversi, report_group, kelompok arus kas.
- `MasterDataSeeder` (2 unit demo + chart of accounts 20 akun mencakup semua system_key, idempotent, satu transaksi, verifikasi system_key) **terpisah** dari `DemoSeeder` (menolak `production`). `DatabaseSeeder` → MasterData saja.

**Jumlah tabel (dokumentasi wajib):** total 31 di `bumdes_munggu` = **22 tabel bisnis** (users + 21 baru) + **9 tabel framework/infra** (migrations, password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, passkeys). `passkeys`/`two_factor_*` bawaan Fortify tetap tak terpakai (fitur off).

**TAHAP03 SELESAI (28 Sep 2026).** Yang dibangun & terverifikasi:
- **MasterData** (unit/akun/pihak/produk): komponen Livewire index (search `#[Url]` + pagination) + modal form (Form object) + nonaktif (bukan hapus). Mutasi lewat Domain Action (`SaveUnit/SaveAccount/SaveParty/SaveProduct`) dengan `authorize('perform-admin-mutation')` — **Pimpinan baca saja** (403 saat mutasi). Guard: akun terpakai tak boleh ubah kode/system_key/jenis/saldo_normal; unit produk terkunci setelah ada mutasi; **stok_terkini/HPP rata tidak diedit dari form** (cache).
- **OpeningBalance**: `OpeningImportParser` (xlsx/csv via PhpSpreadsheet) → `PreviewImport` (validasi per baris + total D/K + status seimbang, pakai bcmath) → `CommitImport` (atomik; **proteksi impor ulang**: ganti isi jika belum lock, tolak jika terkunci) → `OpeningReconciliation` (seimbang + rincian==akun kontrol) → `LockOpeningBalance` (tolak "lock mismatch"; sukses set `opening_locked_at/by` + `locked_at/by` per baris + log). UI: pilih/buat periode cutover, upload+preview (tabel error per baris), commit, panel rekonsiliasi, tambah rincian akun kontrol, tombol kunci. Route + sidebar Data Master diaktifkan.

**TAHAP04 SELESAI (28 Sep 2026).** Yang dibangun & terverifikasi (di `app/Domain/Accounting` & `Shared`):
- `Decimal` (brick/math, HALF_UP, skala uang 2 / qty 4 / HPP 6); `DomainException` berkode (STALE_VERSION, IDEMPOTENCY_CONFLICT, PERIOD_CLOSED, OPENING_NOT_LOCKED, UNBALANCED, REVERSE_DEPENDENCY, INVALID_STATE).
- `JournalLine`/`JournalPlan` + `JournalValidator` (≥2 baris, tiap baris satu sisi, akun aktif, debit=kredit 2 desimal, total>0). `JournalPlanner` (kontrak) + `JournalPlanRegistry` (jenis→planner) + `ManualJournalPlanner` (penyesuaian). Modul tahap05+ mendaftarkan planner masing-masing.
- `PostingService::post` — **satu DB transaction**, idempotency transition (`request_key`+`request_hash` di `log_aktivitas`: key+hash sama → replay; hash beda → 409 conflict), lock urut sumber→periode (`lockForUpdate`), `PeriodGuard` (terbuka + opening terkunci), optimistic `version`, plan **dihitung ulang** & divalidasi, tulis `transaksi_detail` kanonik, `posted_at/by`+`version++`, `periode.revision++`, audit log. Sudah-posted = no-op idempoten.
- `ReverseTransaction::reverse` — alasan wajib, tanggal pembalik pada periode terbuka, baris jurnal **debit/kredit tertukar**, tautan `reversal_of_id`, original → `reversed` **tanpa** mengubah `posted_at` (tetap dihitung di ledger pada tanggalnya).
- `AuditWriter` (log append-only), `LedgerQuery` (saldo akun dari `transaksi_detail` terposting, `posted_at IS NOT NULL`), `SaveManualJournal` (+ Livewire `Accounting\ManualJournal`: buat draft → post → reverse; policy admin) + route/sidebar.

**TAHAP05 BAGIAN 1 SELESAI (28 Sep 2026) — dagang + persediaan/HPP:**
- Ekstensi `PostingStrategy` (extends `JournalPlanner`): `plan()` + `documentTotal()` (total komersial ≠ jumlah debit jurnal) + `applyEffects()` (efek stok/subledger dalam transaksi yang sama). ManualJournalPlanner diperbarui; PostingService memanggil applyEffects & documentTotal.
- Inventory: `MovingAverageCost` (rata-rata tertimbang perpetual, ambil sisa saat habis) + `StockLedger` (mutasi_stok immutable, urutan monotonik, larangan backdate, update cache produk).
- Trading: `SalePostingStrategy` (Dr kas/piutang; Cr penjualan+pajak; Dr HPP; Cr persediaan — nilai keluar aktual; tolak stok kurang) & `PurchasePostingStrategy` (Dr persediaan; Cr kas/utang; stok masuk) + `SystemAccounts` (resolusi akun by system_key) + `SaveTradingDraft`. Terdaftar di `JournalPlanRegistry` (penjualan/pembelian).
- Terverifikasi (dokumen08 §4): beli 10@10rb lalu 10@14rb → rata 12.000; jual 5 → HPP 60.000, saldo 15; penjualan kredit pakai piutang; stok kurang & backdate ditolak + rollback.

**TAHAP05 KAS + PELUNASAN SELESAI (28 Sep 2026):**
- `SaveCashDraft` (pemasukan/pengeluaran per `kategori_kode`; untuk pelunasan menulis `alokasi_pelunasan`), `CashPostingStrategy` (Dr Kas/Cr lawan atau sebaliknya; pelunasan hanya kurangi piutang/utang tanpa pendapatan ganda), `SettlementQuery` (outstanding invoice/opening dari alokasi terposting), terdaftar registry (pemasukan/pengeluaran).
- Terverifikasi: pemasukan jasa (Dr kas/Cr penjualan), pengeluaran beban (Dr beban/Cr kas), pelunasan piutang mengurangi piutang tanpa pendapatan baru (outstanding 100rb→0), **lebih bayar ditolak (OVERPAYMENT)** + rollback.
- **Perbaikan mesin:** `documentTotal` dihitung sebelum `applyEffects` (mencegah recompute atas stok yang sudah berubah pada penjualan yang menghabiskan stok).

**TAHAP05 UI SELESAI (1 Okt 2026):** `Trading\Create` (penjualan/pembelian, satu komponen via route default `jenis`), `Cash\Create` (pemasukan/pengeluaran + pelunasan dengan pilih invoice outstanding), `Accounting\Transactions\Index` (filter jenis/status), `Accounting\Transactions\Show` (**drilldown**: jurnal + barang + kartu stok terkait; tombol Posting, Reverse untuk penyesuaian), `Inventory\Stock` (daftar + kartu stok inline). Route + sidebar Transaksi/Persediaan aktif. DemoSeeder menambah saldo awal cutover terkunci (Kas/Modal) agar posting langsung bisa dicoba. Tes UI: admin buat penjualan→posting (stok 10→5), Pimpinan 403 saat buat & posting.

**TAHAP05 LENGKAP (1 Okt 2026) — bukti + reverse dagang/kas:**
- **Reverse** diperluas: `ReverseTransaction` membalik efek stok generik (mencerminkan `mutasi_stok` asli via `StockLedger::reverseMovement`) + guard dependensi (mutasi terbaru per produk; invoice dengan pelunasan terposting harus dibalik dulu). Pelunasan yang dibalik otomatis memulihkan outstanding (filter status posted). Reverse di `Show` aktif untuk semua jenis.
- **Bukti**: `UploadEvidence` (disk private `local`, sha256, MIME/5MiB), `VerifyEvidence` (verify/reject), Gate `verify-evidence` (Pimpinan atau Admin bukan pengunggah), `EvidenceDownloadController` (attachment, route auth), UI unggah/verifikasi/tolak/unduh di `Show`.
- Terverifikasi: reverse penjualan mengembalikan stok (5→10), tolak bila ada mutasi lebih baru / pelunasan belum dibalik, reverse pelunasan memulihkan outstanding (0→100rb); unggah bukti ke disk private, Pimpinan verifikasi, pengunggah tak boleh verifikasi bukti sendiri, tolak dengan alasan.

**Next task konkret (tahap06 — dokumen 06/08/11 fixture C):**
1. Pinjaman: kontrak + `basis_bunga` wajib (flat_total/flat_bulanan), total bunga, jadwal bulanan (tanggal jangkar tanpa drift, selisih pembulatan ke jadwal terakhir), pencairan (planner `pencairan`).
2. Angsuran: pembayaran sebagian pokok/bunga/denda via `pembayaran_angsuran` (histori), cache angsuran/sisa_pokok diverifikasi dari histori, reverse alokasi.
3. UI pinjaman/jadwal/pembayaran. Uji fixture C dokumen11. Jangan ganti pinjaman dengan piutang penjualan / tabungan.

**Catatan:** Chart of accounts (`config/accounting.php` + seeder) masih **[TEKNIS] menunggu validasi pengelola** sebelum data nyata. PeriodGuard memakai model cutover; relaksasi carry-forward multi-periode = tahap09.

## Catatan sesi

| Tanggal | Tahap | Perubahan | Tes benar-benar dijalankan | Hasil / blocker | Next task |
|---|---|---|---|---|---|
| 2026-09-28 | 00 | Baca dokumen 01/02/03/15 + copilot-instructions; verifikasi environment di komputer pengguna; update Environment aktual & Task aktif. Tidak ada kode aplikasi dibuat. | Tidak ada tes aplikasi (belum ada kode); hanya perintah versi environment: `php -v`, `composer --version`, `php -m`, `php --ini`, `node/npm/git --version`, `mysql --version`, tes port 3306 | PHP 8.2.12 & Composer 2.6.6 cocok target; ekstensi wajib lengkap (intl absen, non-wajib). **Blocker:** engine MariaDB 10.4.32 ≠ MySQL 8.4 target. Minor: npm 8.15.0 tua utk Node 22. | Tahap01 scaffold Laravel (bagian migrasi/tes DB menunggu keputusan engine) |
| 2026-09-28 | 00→01 | Keputusan engine (b): target = MariaDB 10.4.32. Selaraskan seluruh dokumen (copilot-instructions, dokumen02/03/04/11/12/13/14/15, README) dari MySQL 8.4 → MariaDB; tambah ADR-23 + subseksi kompatibilitas MariaDB 10.4 di dokumen03; set `DB_CONNECTION=mariadb`. Tidak ada kode/migrasi dijalankan; tidak ada DB dihapus. | Verifikasi DB read-only: `SELECT VERSION()` = 10.4.32-MariaDB; `SHOW DATABASES`/`information_schema` (bumdes_munggu ada & kosong). Tidak ada tes aplikasi (belum ada kode). | Koneksi MariaDB OK; `bumdes_munggu` kosong; `bumdes_munggu_test` belum ada. Tidak ada error. | Tahap01: scaffold Laravel 12 + auth (lihat langkah di bawah); lalu buat `bumdes_munggu_test` + `ALTER DATABASE` collation saat tahap DB |
| 2026-09-28 | 01 | Scaffold Laravel 12.69.2 terpin (platform.php 8.2.12); +Livewire 3.8.9, Fortify 1.40.0. `.env` MariaDB. users.nama+role+status (enum), EnsureUserActive (alias+persistent), Gate admin, CLI app:create-user (password tersembunyi), Fortify login-only (registrasi off, tolak nonaktif), layout app/guest + sidebar/header/breadcrumb, komponen ui (button/input/card/badge/table/modal), dashboard kosong jujur, Tailwind4/Vite build. DB user `bumdes_app` + `bumdes_munggu_test` dibuat; collation `bumdes_munggu` diselaraskan. Salin docs/copilot-instructions/PROGRESS ke root aplikasi. | `composer check-platform-reqs` (lulus), `php artisan migrate` (10 tabel), `php artisan db:show` (mariadb 10.4.32), `npm install` + `npm run build` (Vite 7.3.6, manifest OK), `php artisan view:cache` (OK), `vendor/bin/pint` (1 fix), **`php artisan test` = 19 passed / 52 assertions** di MariaDB (`bumdes_munggu_test`). | Semua lulus. Login/logout, tolak user nonaktif (saat login & mid-session), larangan mutation Pimpinan (403), CLI, dashboard render — terverifikasi. Sisa: kolom `two_factor_*`+tabel `passkeys` bawaan Fortify tak terpakai; git belum diinisialisasi. | Tahap02: 18 inti + 4 pendukung migration/model/seed di MariaDB test |
| 2026-09-28 | 02 | 21 migration tabel bisnis + 1 migration FK-silang; 21 model Eloquent (cast enum/decimal/array, relasi, `UPDATED_AT=null` utk append-only); 17 enum backed; `config/accounting.php` (20 system_key + mapping kategori); `MasterDataSeeder` (2 unit + 20 akun) & `DemoSeeder` (tolak production); factory unit/periode/akun/pihak/produk/transaksi/detail. | `php artisan migrate` (22 tabel bisnis, generated column + 14 CHECK OK di MariaDB), `db:seed MasterDataSeeder` (units=2, akun=20, system_keys=20), `vendor/bin/pint` (passed), **`php artisan test` = 30 passed / 109 assertions** di `bumdes_munggu_test` (schema, 6 constraint, 3 seeder + tahap01). | Semua lulus. Migration/CHECK/unique/generated-column terverifikasi di MariaDB nyata; seeder idempotent & semua system_key aktif. Total 31 tabel = 22 bisnis + 9 framework. | Tahap03: MasterData UI + OpeningBalance import/rincian/lock |
| 2026-09-28 | 02→03 | git init + baseline commit `ed53039` (tahap00–02). | — (operasi git; verifikasi `git log`/`git status` bersih) | Repo lokal `main` siap; `.env`/vendor/node_modules diabaikan. | Tahap03 |
| 2026-09-28 | 03 | +phpoffice/phpspreadsheet. MasterData: 4 Form object + 4 Livewire (Units/Accounts/Parties/Products) + 4 view + 4 Domain Action + guard akun-terpakai/produk-unit. OpeningBalance: parser + PreviewImport + CommitImport + OpeningReconciliation + LockOpeningBalance + Livewire Index + view. Route + sidebar Data Master aktif. Rebuild Vite. DemoSeeder di dev (periode/pihak/produk). | `php artisan route:list` (23 route), `npm run build` (manifest OK), `vendor/bin/pint` (fixed 2), **`php artisan test` = 47 passed / 145 assertions** di `bumdes_munggu_test` (MasterData CRUD/policy/guard, OpeningBalance preview-error/commit-atomik/reimport/parser CSV, lock: seimbang/unbalanced/rincian-mismatch/relock) + tahap01–02. | Semua lulus. Pimpinan tertolak mutasi (403); impor invalid → error per baris tanpa commit; lock mismatch (unbalanced & rincian≠kontrol) ditolak; impor ulang setelah lock ditolak. | Tahap04: PostingService/Reverse/PeriodGuard/Audit |
| 2026-09-28 | 03→git | Commit tahap03 `a37d2ac`. | — | Repo `main` bersih. | Tahap04 |
| 2026-09-28 | 04 | +brick/math (direct). Domain Accounting: Decimal, DomainException berkode, JournalLine/JournalPlan, JournalValidator, JournalPlanner+Registry+ManualJournalPlanner, PeriodGuard, AuditWriter, PostingService, ReverseTransaction, LedgerQuery, SaveManualJournal. Livewire Accounting\ManualJournal + view + route + sidebar. Rebuild Vite. Tanpa migration baru. | `vendor/bin/pint` (passed), `php artisan route:list` (route jurnal OK), `npm run build` (manifest OK), **`php artisan test` = 62 passed / 181 assertions** di `bumdes_munggu_test` (PostingService: post/idempotent/hash-konflik/unbalanced-rollback/period-closed/opening-not-locked/version; Reverse: swap+link/lintas-periode ledger/alasan/double/draft; Livewire ManualJournal: admin post, pimpinan 403 save & post). | Semua lulus. Idempotency replay & konflik 409 terverifikasi; rollback saat unbalanced (0 log); reversed original tetap dihitung 30 Jun (100000) & neto 0 di 31 Jul; Pimpinan tertolak. | Tahap04 commit `d2a06d5`; lalu Tahap05 |
| 2026-09-28 | 04→git | Commit tahap04 `d2a06d5`. | — | Repo `main` bersih. | Tahap05 |
| 2026-09-28 | 05 (Bag.1) | Refactor PostingStrategy (plan+documentTotal+applyEffects); PostingService pakai applyEffects+documentTotal. Inventory: MovingAverageCost, StockLedger. Trading: SystemAccounts, SalePostingStrategy, PurchasePostingStrategy, SaveTradingDraft; daftar strategy penjualan/pembelian ke registry. Tanpa migration baru. | `vendor/bin/pint` (fixed 2 import), **`php artisan test` = 67 passed / 201 assertions** di `bumdes_munggu_test` (Trading: purchase+moving-average, sale+HPP, kredit→piutang, insufficient-stock rollback, backdate rollback; + tahap01–04). | Semua lulus. Rata-rata 12.000 & HPP 60.000 cocok dokumen08; jurnal seimbang (kas/penjualan + HPP/persediaan); stok negatif & backdate ditolak + rollback. **Sisa tahap05:** kas, pelunasan (overpayment), bukti, UI dagang, reverse dagang. | Tahap05 bag.1 commit `311ad7a`; lalu kas/pelunasan |
| 2026-09-28 | 05→git | Commit tahap05 bagian 1 `311ad7a`. | — | Repo `main` bersih. | Kas + pelunasan |
| 2026-09-28 | 05 (kas) | Cash: SaveCashDraft, CashPostingStrategy (pemasukan/pengeluaran per kategori + pelunasan piutang/utang), SettlementQuery; daftar strategy pemasukan/pengeluaran. Perbaikan PostingService: documentTotal sebelum applyEffects. Tanpa migration baru. | `vendor/bin/pint` (fixed 1 import), **`php artisan test` = 71 passed / 214 assertions** di `bumdes_munggu_test` (Cash: pemasukan jasa, pengeluaran beban, pelunasan tanpa pendapatan ganda, overpayment ditolak; + semua tahap sebelumnya). | Semua lulus. Pelunasan mengurangi piutang (outstanding 100rb→0) tanpa pendapatan baru; lebih bayar ditolak + rollback; bug recompute penjualan-habis-stok diperbaiki. **Sisa tahap05:** bukti privat, reverse dagang, UI. | Commit `c26fc1b`; lalu UI |
| 2026-10-01 | 05 (UI) | Livewire: Trading\Create (penjualan/pembelian), Cash\Create (+pelunasan pilih invoice), Accounting\Transactions\Index + Show (drilldown jurnal+kartu stok, post/reverse), Inventory\Stock (+kartu stok). Route + sidebar Transaksi/Persediaan. DemoSeeder: saldo awal cutover terkunci (Kas/Modal). Tanpa migration baru. | `php artisan route:list` (6 route baru), `vendor/bin/pint` (passed/minor), `npm run build` (manifest OK), `db:seed DemoSeeder` (periode Jan terkunci), **`php artisan test` = 74 passed / 223 assertions** (TradingUiTest: buat penjualan→posting stok 10→5, Pimpinan 403 buat & posting; + semua sebelumnya). | Semua lulus. Alur dagang end-to-end lewat UI terverifikasi; Pimpinan tertolak; drilldown jurnal+kartu stok tampil. **Sisa tahap05:** bukti privat + reverse dagang/kas. | Commit `4b538cd`; lalu bukti+reverse |
| 2026-10-01 | 05 (bukti+reverse) | Reverse: StockLedger::reverseMovement + ReverseTransaction (balik stok generik + guard mutasi-terbaru & pelunasan-dulu); Show reverse aktif semua jenis. Bukti: UploadEvidence (disk private+sha256), VerifyEvidence, Gate verify-evidence, EvidenceDownloadController + route; UI unggah/verify/tolak/unduh di Show. Tanpa migration baru. | `vendor/bin/pint` (fixed import), `npm run build` (OK), **`php artisan test` = 82 passed / 246 assertions** (ReverseTradingTest 4: stok kembali, dependensi mutasi/pelunasan, outstanding pulih; EvidenceTest 4: unggah private, verifikasi Pimpinan, pengunggah ditolak, tolak beralasan; + semua sebelumnya). | Semua lulus. **TAHAP05 LENGKAP.** | Tahap06: pinjaman/jadwal/angsuran |

## Keputusan operasional sebelum data nyata

- [x] **Engine database final: MariaDB 10.4.32** (XAMPP) — diputuskan 28 Sep 2026, ADR-23; driver `mariadb`, collation `utf8mb4_unicode_ci`, strict mode on
- [ ] Chart of accounts dan mapping laporan
- [ ] Metode HPP
- [ ] Basis bunga dan denda
- [ ] Saldo cutover dan seluruh rincian
- [ ] Awal penyusutan
- [ ] Kebijakan pajak jika diperlukan
- [ ] Policy bukti/approval
- [ ] Revisi PDM untuk tabel pendukung

## Bukti selesai

Isi commit SHA, perintah build/test, tanggal UAT, tester, nilai rekonsiliasi, dan hasil restore. Jangan mengisi hasil fiktif.

