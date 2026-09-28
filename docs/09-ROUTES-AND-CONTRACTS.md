# 09 — Routes dan kontrak Livewire/Domain

## Transport utama

GET web route menuju full-page Livewire component dengan auth dan middleware pengguna aktif. Interaksi form memakai public method Livewire melalui mekanisme update bawaan. Komponen memvalidasi input, authorize actor+record, lalu memanggil Domain Action. Jangan membuat REST endpoint paralel untuk setiap method form tanpa kebutuhan nyata.

Auth menggunakan route Fortify untuk login/logout session dan CSRF. Download bukti/ekspor menggunakan Controller dan policy. Tidak membutuhkan token JWT atau penyimpanan token di browser.

Semua nominal berupa decimal string canonical ("1250000.00"), kuantitas string4 desimal. Tanggal bisnis YYYY-MM-DD; timestamp audit UTC; tampilan Asia/Makassar. Query URL boleh berisi period_id, unit_id, date_from, date_to, status, q, sort, direction, page. Unit kosong=konsolidasi. Periode dan rentang eksplisit yang bertentangan ditolak, bukan dipilih diam-diam.

## Route halaman

| GET path / route name | Component / fungsi | Hak |
|---|---|---|
| /dashboard / dashboard | Dashboard\\Index | Kedua role |
| /master/units / units.index | MasterData\\Units\\Index | Kedua; mutasi Admin |
| /master/accounts / accounts.index | MasterData\\Accounts\\Index | Kedua; mutasi Admin |
| /master/parties / parties.index | MasterData\\Parties\\Index | Kedua; mutasi Admin |
| /master/products / products.index | MasterData\\Products\\Index | Kedua; mutasi Admin |
| /opening-balances | OpeningBalance\\Index | Kedua; input/import/lock Admin |
| /sales / sales.index | Trading\\Sales\\Index | Kedua |
| /sales/create / sales.create | Trading\\Sales\\Create | Admin |
| /purchases / purchases.index | Trading\\Purchases\\Index | Kedua |
| /purchases/create | Trading\\Purchases\\Create | Admin |
| /cash-in / /cash-out | Cash\\Receipts\\Index / Payments\\Index | Kedua |
| /cash-in/create / /cash-out/create | Form penerimaan/pengeluaran | Admin |
| /transactions / transactions.index | Accounting\\Transactions\\Index | Kedua |
| /transactions/{transaction} | Accounting\\Transactions\\Show | Kedua |
| /transactions/{transaction}/edit | Form sesuai jenis transaksi draft | Admin |
| /loans / /loans/{loan} | Lending\\Loans\\Index / Show | Kedua |
| /loans/create | Lending\\Loans\\Create | Admin |
| /installments | Lending\\Installments\\Index | Kedua |
| /installment-payments/create | Lending\\Installments\\PaymentForm | Admin |
| /inventory / /stock-cards | Inventory\\Stock\\Index / StockCards\\Index | Kedua |
| /stocktakes / /stocktakes/{stocktake} | Inventory\\Stocktakes\\Index / Show | Kedua |
| /stocktakes/create | Inventory\\Stocktakes\\Create | Admin |
| /assets / /assets/{asset} | FixedAssets\\Index / Show | Kedua |
| /assets/create | FixedAssets\\Create | Admin |
| /depreciation | FixedAssets\\Depreciation | Kedua; post Admin |
| /journals / /general-ledger | Accounting\\Journals\\Index / GeneralLedger\\Index | Kedua |
| /periods | Accounting\\Periods\\Index | Kedua |
| /reports/{report} | Reporting\\Show; report allowlist | Kedua |
| /village-reports / /village-reports/{report} | VillageReport\\Index / Show | Kedua |
| /evidence | Evidence\\Index | Kedua |
| /audit-logs / /reconciliation | Audit\\ActivityIndex / Reconciliation | Kedua |

Daftarkan route statis /create sebelum parameter /{id}. Gunakan route model binding terkontrol atau locked ID dan load server; jangan menerima nama model arbitrer dari parameter. Jika daftar transaksi belum ada pada folder architecture, tambahkan app/Livewire/Accounting/Transactions secara konsisten.

## Public method Livewire dan Action

Nama method tidak dianggap aman hanya karena tidak ada tombol. Setiap method wajib policy, input validation dan pengecekan status sumber.

| Component/use case | Public method | Domain Action | Hak |
|---|---|---|---|
| Master form | save(), deactivate() | SaveMaster/DeactivateMaster | Admin |
| Opening form | previewImport(), commitImport(), lockOpening() | PreviewImport/CommitImport/LockOpeningBalance | Admin |
| Sale/purchase/cash form | saveDraft() | SaveSaleDraft/SavePurchaseDraft/SaveCashDraft | Admin |
| Detail transaksi | previewJournal(), post(), reverse() | PreviewJournal/PostTransaction/ReverseTransaction | Admin |
| Pinjaman | saveDraft(), disburse() | SaveLoanDraft/DisburseLoan | Admin |
| Pembayaran angsuran | saveDraft() | SaveInstallmentPayment | Admin |
| Denda jadwal | setPenalty() | SetInstallmentPenalty | Admin |
| Opname | save(), complete(), submit(), post() | Stocktake Actions | Admin |
| Opname review | approve(), requestRevision() | ApproveStocktake/RequestStocktakeRevision | Pimpinan |
| Aset | save(), previewDepreciation(), postDepreciation() | Asset Actions | Admin |
| Periode | open() | OpenPeriod | Kedua |
| Periode | close(), reopen() | ClosePeriod/ReopenPeriod | Pimpinan |
| Laporan | requestExport() | RequestExport | Kedua |
| Omzet desa | createSnapshot(), submit(), markSent(), markReceived() | VillageReport Actions | Admin |
| Omzet review | approve(), requestRevision() | ApproveReport/RequestReportRevision | Pimpinan |
| Bukti | upload(), verify(), reject() | Evidence Actions | Sesuai policy |

## Endpoint HTTP khusus

| Method/path | Implementasi |
|---|---|
| GET/POST /login | Fortify view/action, rate limit |
| POST /logout | Fortify session logout, CSRF |
| GET /evidence/{evidence}/download | EvidenceDownloadController; auth+policy; private stream |
| GET /exports/{exportToken} | ExportDownloadController; auth+policy, token bukan otorisasi tunggal |
| GET /up | Health route tanpa detail kredensial |

Endpoint /livewire/update dan temporary uploads dikelola Livewire; jangan menulis handler custom yang melewati proteksi framework. Endpoint JSON lookup hanya dibuat jika komponen tertentu memerlukannya; default query lookup langsung melalui component Query.

## Kontrak DTO penjualan

Bentuk berikut adalah data tervalidasi yang diserahkan component ke Action, bukan body REST publik:

```php
[
    'request_key' => 'uuid-valid',
    'tanggal' => '2026-06-15',
    'unit_usaha_id' => 1,
    'pihak_id' => 7,
    'metode_pembayaran' => 'kredit',
    'akun_kas_id' => null,
    'jatuh_tempo' => '2026-07-15',
    'items' => [
        ['produk_id' => 12, 'kuantitas' => '2.0000',
         'harga' => '25000.00', 'diskon' => '0.00', 'pajak' => '0.00'],
    ],
    'keterangan' => 'Penjualan barang',
]
```

ID contoh harus mengacu data nyata saat dijalankan. Server menghitung total50.000 dan HPP dari stok; client tidak menentukan posted_by/status/akun jurnal. Bukti diunggah setelah draft memiliki ID, sebelum posting.

## Preview dan post

PreviewData memuat transaction_id, version, total, journal rows (akun,debit,kredit), stock effects, balanced, warnings dan preview_hash. Simpan hanya data aman untuk tampilan. Input post memuat request_key, source ID, expectedVersion, preview_hash; Action reload source dan hitung ulang dari data server.

request_key harus tetap untuk retry aksi yang sama, bukan dibuat ulang setiap render. Key create dan key post berbeda. Key sama/hash sama mengembalikan hasil terdahulu; key sama/payload beda menghasilkan konflik. Constraint, lock dan log idempotency mengikuti dokumen05.

## Kontrak pembayaran angsuran

Input tervalidasi: tanggal, akun_kas_id, pinjaman_id, request_key, allocations[{angsuran_id,pokok,bunga,denda}]. Unit/pihak diturunkan dari pinjaman. Tolak alokasi lintas pinjaman dan lebih bayar. Simpan draft, unggah bukti, lalu post melalui Action yang sama seperti transaksi lain.

## Penanganan error

| Kondisi | Kode domain | Perilaku UI |
|---|---|---|
| Belum login/session habis | UNAUTHENTICATED | Login ulang, tidak meneruskan mutasi |
| Pengguna nonaktif/role salah | FORBIDDEN |403; tidak ada efek data |
| Record tidak ditemukan | NOT_FOUND |404 |
| Input salah | VALIDATION_ERROR | Error bag via @error |
| Versi/preview berubah | STALE_VERSION/STALE_PREVIEW | Pesan konflik, refresh/preview ulang |
| Periode tertutup | PERIOD_CLOSED | Tolak dan jelaskan periode |
| Stok kurang | INSUFFICIENT_STOCK | Error dekat baris produk |
| Bayar berlebih | OVERPAYMENT | Error alokasi terkait |
| Key beda isi | IDEMPOTENCY_CONFLICT | Tolak retry dengan payload berbeda |
| Reverse punya dependensi | REVERSE_DEPENDENCY | Tampilkan sumber penghalang |
| Penyusutan duplikat | DEPRECIATION_EXISTS | Link transaksi yang sudah ada |

Domain exception dipetakan ke addError atau halaman403/404 yang tepat; konflik domain tidak harus menghasilkan raw HTTP409 pada protokol Livewire. Jangan mengubah response update secara sembarang hingga hydration rusak. Unexpected exception dicatat dengan request/correlation ID dan pesan umum; jangan tampilkan SQL/stack trace. Tes asserting domain code/result atau validation errors, bukan mengandalkan HTTP status200 update sebagai bukti sukses bisnis.

## State workflow

Transaksi draft→posted→reversed, dengan satu pembalik posted. Opname draft→selesai→menunggu_persetujuan→disetujui→posted; revisi kembali draft dan approval dibersihkan. Omzet desa draft→diajukan→disetujui→dikirim→diterima; revisi setelah approval membuat versi baru. Public property status tidak boleh langsung disimpan ke model.
