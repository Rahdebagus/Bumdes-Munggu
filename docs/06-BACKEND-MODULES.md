# 06 — Backend dan batas tanggung jawab

## Kontrak kelas

Livewire component: authorize → validasi Form object/rules → DTO → Action/Query → render Blade/flash/redirect. Controller download/HTTP khusus: authorize → FormRequest bila perlu → Action/Query → response. Tidak menaruh rumus akuntansi di Controller, Livewire component, Model observer, atau view. Livewire public method tidak otomatis memanggil FormRequest; shared rules dan invariant domain wajib dipakai.

| Modul | Action utama | Query / service |
|---|---|---|
| Identity | CreateUser, DisableUser | ActiveUserGuard |
| MasterData | SaveUnit, SaveAccount, SaveParty, SaveProduct | MasterLookupQuery |
| OpeningBalance | PreviewImport, CommitImport, LockOpeningBalance | OpeningReconciliation |
| Trading | SaveSaleDraft, SavePurchaseDraft | TradingJournalBuilder |
| Cash | SaveCashDraft, AllocateSettlement | CashJournalBuilder |
| Lending | SaveLoanDraft, DisburseLoan, SaveInstallmentPayment | ScheduleCalculator, LoanBalanceQuery |
| Inventory | StartStocktake, SubmitStocktake, ApproveStocktake, PostStocktake | MovingAverageCost, StockCardQuery |
| FixedAssets | RegisterAsset, PreviewDepreciation, PostDepreciation | DepreciationCalculator |
| Accounting | PostTransaction, ReverseTransaction | LedgerQuery, JournalValidator |
| Periods | OpenPeriod, ClosePeriod, ReopenPeriod | PeriodGuard |
| Reporting | RequestExport | SixReportQueries |
| VillageReport | CreateSnapshot, SubmitReport, ApproveReport, MarkSent, MarkReceived | SnapshotBuilder |
| Evidence | UploadEvidence, VerifyEvidence | AuthorizedDownload |
| Audit | AppendActivity, RunReconciliation | ReconciliationChecks |

Action domain memanggil PostingService; tidak menduplikasi mekanisme lock/jurnal. Strategy per jenis transaksi menghitung journal plan, inventory plan, dan subledger plan dari data server.

## Posting atomik

Urutan lock konsisten seluruh modul: periode → transaksi/sumber (ID naik) → produk (ID naik) → pinjaman/jadwal/aset/target pelunasan (ID naik). Gunakan DB transaction dan `lockForUpdate`. Operasi close/reopen juga lock periode yang sama. Lock periode sengaja menyerialisasi posting dalam periode untuk skala kecil; optimasi baru setelah kebutuhan terbukti.

```text
post(transactionId, expectedVersion, actor):
  begin database transaction
  lock period; require open and cutover opening balance locked
  lock source transaction; authorize actor and validate expectedVersion
  if already posted: return existing result (no new journal)
  if reversed: reject
  lock every affected product/loan/schedule/invoice/asset in fixed order
  validate accounts, source references, quantities, evidence policy
  recompute totals and journal plan with Decimal
  validate debit = credit exactly to 2 decimals and total > 0
  validate stock nonnegative, subledger allocations, due dates, duplicate keys
  insert journal lines, stock movements, payment allocations
  update derived caches and source domain statuses
  mark posted_at/by; increment period revision and source version
  append activity log
  commit
  dispatch only nonfinancial after-commit jobs
```

Preview journal bukan otorisasi posting: saat post semua nilai dihitung ulang. Bila harga pokok berubah setelah preview, hasilkan domain conflict dan tampilkan preview baru, jangan posting diam-diam berdasarkan nilai lama yang dilihat pengguna.

Kegagalan di langkah mana pun mengembalikan seluruh perubahan DB. File yang sudah diunggah memiliki proses cleanup tersendiri; jangan menghapus file audit transaksi posted karena rollback request lain.

## Reverse

Require reason, tanggal pembalik dalam periode terbuka, status sumber posted, dan belum punya reversal. Copy baris jurnal dengan debit/kredit tertukar; hubungkan reversal_of_id; posted_at original tetap. Mutasi stok/alokasi pembayaran juga dibuat lawan dengan referensi sumber.

Original menjadi reversed, pembalik posted. Ledger query **menyertakan original reversed** karena transaksi itu pernah diposting. Query `status=posted` saja salah setelah reverse. Gunakan `posted_at IS NOT NULL AND tanggal <= cutoff` dengan batas periode yang benar; draft selalu posted_at null.

Batasi reverse pembelian/penjualan ke sumber yang mutasi stoknya masih paling akhir untuk setiap produk. Jika ada mutasi lanjutan, tolak dengan pesan urutan dependensi; reverse lanjutan terlebih dahulu jika memang koreksi diperlukan. Blueprint ini tidak membuat algoritma revaluasi HPP historis otomatis. Transaksi penjualan kredit dengan pelunasan harus membalik pelunasan dulu; pencairan dengan angsuran harus membalik angsuran dulu. Semua tolak bersifat atomik.

Lock saldo awal mengacu pada batch cutover yang berlaku, bukan menuntut input saldo awal baru setiap bulan. Periode berikutnya memakai saldo bawa hasil ledger.

## Penguncian dan pengawasan

- Edit draft memakai optimistic `version`; versi stale → domain conflict (dipetakan ke error bag Livewire).
- Close period memeriksa draft tertinggal, saldo awal, penyusutan yang wajib, opname yang belum selesai, dan seluruh rekonsiliasi. FAIL memblokir; WARNING membutuhkan alasan pengakuan Pimpinan. Posting tidak dapat menyelinap di tengah penutupan.
- Reopen oleh Pimpinan dengan alasan, audit, revision++. Snapshot disetujui tidak diubah; tampilkan live revision berbeda dan buat versi revisi jika diperlukan.
- Master akun yang digunakan: larang perubahan kode/system_key/jenis/saldo_normal yang mengubah makna historis. Nama tampilan boleh berubah dengan audit; snapshot resmi menyimpan nama lama.
- Role bukan sekadar menu tersembunyi: Policy pada action, route, unduh, ekspor, approval.

## Bukti transaksi

PDF/JPEG/PNG maksimum 5 MiB per file [TEKNIS]. Periksa MIME dari konten dan ekstensi, batasi jumlah/ukuran total, acak nama penyimpanan. Tolak SVG, executable, dan file yang tidak sesuai MIME. Simpan di disk private, download melalui controller terautentikasi dengan Content-Disposition attachment. Original filename untuk tampilan saja, tidak digabung sebagai path.

Bukti minimal satu pada transaksi kas/dagang/pencairan/angsuran; transaksi sistem penyusutan/opname memakai dokumen internal. Bukti ditolak memblokir post. Bukti belum diperiksa boleh post dengan WARNING [ASUMSI], tetapi seluruh bukti pendukung laporan desa harus valid sebelum persetujuan. Perubahan kebijakan cukup pada policy config; jangan menyebar if ke banyak controller.

## Keamanan dan operasional

Session auth same-origin, CSRF aktif, cookie HttpOnly/Secure pada production. Tidak menyimpan token auth di localStorage. Regenerate session setelah login; invalidasi setelah logout/nonaktif. Login rate limit. Validasi mass assignment dan allowlist sort/filter. Cegah akses objek lintas jenis transaksi dengan scoped route binding.

Nominal/role/status/posted_by dari client tidak dipercaya. DTO hanya memuat field yang boleh diedit. Jangan menerima journal lines arbitrer pada endpoint penjualan. Transaksi yang menyentuh akun kontrol persediaan/piutang/pinjaman/aset hanya lewat workflow yang membentuk subledger terkait.

Log error memakai correlation ID, tanpa password/NIK/file bytes. Endpoint `/up` untuk health; pemeriksaan DB/queue internal tanpa menampilkan kredensial. Ekspor besar via queue, retry idempotent, akses unduh hanya peminta atau role berhak. Expiry ekspor tidak menghapus bukti/snapshot resmi.

## Pengujian wajib backend

Policy matrix, posting dua kali, request hash mismatch, race stok, lock periode, rollback setelah jurnal sebelum mutasi, reverse lintas periode, pembayaran parsial, penyusutan duplikat, dan unduh bukti tanpa hak. Lihat dokumen 11.

## Keamanan transport Livewire

Authorize setiap public mutation method, bukan hanya mount(). Property #[Locked] membantu mencegah perubahan ID tetapi tidak menggantikan Policy. Load source dan actor aktif lagi di Action. Middleware status pengguna harus persisten untuk update request. Public properties tidak boleh berisi rahasia/path private. Error domain dipetakan ke error bag/flash; jangan memperlakukan semua response HTTP200 Livewire sebagai keberhasilan transaksi. Pengujian harus memeriksa hasil ledger dan domain.
