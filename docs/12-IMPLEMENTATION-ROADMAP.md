# 12 — Roadmap implementasi dan pengendalian proyek

## Prinsip pelaksanaan

Kerjakan vertical slice: migration → action → policy → Livewire component/route → Blade UI → test → dokumentasi untuk satu use case. Jangan mengerjakan seluruh UI dulu lalu mengisi backend belakangan. Satu tahap selesai jika exit criteria terbukti. Estimasi di bawah adalah perencanaan untuk satu pengembang yang memahami Laravel, bukan janji selesai otomatis oleh Copilot.

| Tahap | Output | Dependensi | Estimasi hari kerja | Exit gate |
|---|---|---|---:|---|
| 00 | Scope, ADR, pemeriksaan environment | — | 0,5–1 | Pilihan stack dan celah bisnis tercatat |
| 01 | Scaffold, auth, policy, dashboard Blade/Livewire | 00 | 1–2 | Login dua role, Livewire aktif, build lulus |
| 02 | Schema inti+ekstensi, seed akun/unit | 01 | 2–3 | Migration MariaDB dan constraint diuji |
| 03 | Master dan saldo awal/import | 02 | 2–3 | Lock opening & rincian cocok |
| 04 | Posting/reverse/period guard/audit core | 03 | 3–4 | Atomicity/idempotency/reverse tests |
| 05 | Dagang, moving average, kas, pelunasan | 04 | 3–5 | Alur tunai/kredit dan stock race lulus |
| 06 | Pinjaman, jadwal, pembayaran sebagian | 04–05 | 2–4 | Pembulatan dan reverse alokasi lulus |
| 07 | Opname approval, aset, penyusutan | 05 | 2–4 | Stale snapshot dan duplicate guard lulus |
| 08 | Enam laporan, dashboard live, PDF/XLSX | 05–07 | 3–5 | Fixture A dan ekspor cocok |
| 09 | Snapshot desa, audit, close/reopen | 08 | 2–3 | Approval version dan periode race lulus |
| 10 | QA lintas modul, UAT, perbaikan | 09 | 3–5 | Critical/high=0, UAT tercatat |
| 11 | Staging, migrasi awal, deploy, handover | 10 | 1–3 | Restore drill dan smoke test lulus |

Total indikatif 24,5–42 hari kerja, sekitar5–9minggu; dapat berubah oleh kualitas data awal dan revisi. Copilot tidak boleh menandai semua tahap selesai hanya karena scaffolding berhasil.

## Milestone yang dapat direview

M1: login + shell + database. M2: satu pembelian dan penjualan posted lengkap jurnal/stok. M3: pinjaman+angsuran dan aset/opname. M4: enam laporan+approval+audit. M5: UAT dan staging. M6: production dan handover.

Setiap milestone sertakan commit SHA, daftar file inti, hasil tes, screenshot responsif seperlunya, known issues, dan cara reproduksi demo. Tidak memerlukan screenshot setiap perubahan kecil.

## Risiko dan penanganan

| Risiko | Penanganan |
|---|---|
| PDF dan kebutuhan lama berbeda | PDF terbaru menjadi baseline; catat perubahan pada ADR |
| 18 tabel tidak cukup menyimpan histori | Tambahan4 tabel terdokumentasi; update PDM akademik |
| Aturan bunga/HPP belum disahkan | Konfigurasi berversi, demo sintetis, review sebelum data nyata |
| Saldo awal tidak dapat direkonsiliasi | Tunda lock/post operasional; perbaiki data import |
| Copilot membuat semua logika di controller | Review batas modul dan tes domain setiap tahap |
| Post/reverse ganda | DB transaction, lock, unique key, tes konkurensi |
| UI menampilkan laporan berbeda dari export | Satu ReportService dan fixture pembanding |
| Upgrade package mengubah API | Lockfile, upgrade PR terpisah, kompatibilitas diperiksa |
| Data sensitif masuk repository | .env/storage tidak commit; fixture sintetis |

## Manajemen perubahan

Issue harus berisi masalah, perilaku saat ini, perilaku harapan, modul terdampak, perubahan schema, pengaruh histori, dan acceptance test. Perubahan nominal historis tidak dilakukan dengan UPDATE massal tanpa prosedur migrasi/koreksi yang diaudit.

Untuk bug, buat reproduction terkecil; perbaiki pada domain pemilik; jalankan tes terdampak dan invariant ledger; catat pada PROGRESS. Jangan refactor seluruh proyek bersamaan dengan satu perbaikan kecil.

## Kriteria siap operasional

Bukan hanya halaman tampil. Pengelola mengonfirmasi COA/mapping, bunga, saldo awal/rincian, metode HPP, cutover, dan awal penyusutan; role riil dibuat; data demo dibuang dari production; backup/restore terbukti; panduan admin dan pimpinan tersedia. Approval deployment mengikuti kewenangan pemilik lingkungan.
